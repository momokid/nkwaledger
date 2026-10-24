<?php

namespace App\Services\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// the one place a loan is created, validated and posted. Loans are never edited: a mistake is cancelled
class LoanService
{
    public const AMOUNT_REFUSAL = 'Enter an amount above zero.';
    public const FUTURE_REFUSAL = 'This date is in the future.';
    public const FARMS_REFUSAL = 'The farm amounts must add up to the loan amount.';
    public const PURPOSE_REFUSAL = 'Please say what this money is for.';
    public const PURPOSE_NOTE_REFUSAL = 'Please say what you will use it for.';

    // the last choice in the list of purposes: the farmer types what it is for
    public const OTHER_PURPOSE = 'other';

    // what the form says about the lender; the loan itself keeps only a link to it
    public const LENDER_INPUT = ['lender', 'lender_type', 'lender_name'];

    // the accounts a farmer may say the money was paid into, and the words the form shows for them
    public const PAID_INTO_LABELS = [
        'Cash A/C' => 'Cash',
        'Momo A/C' => 'MoMo',
        'Bank A/C' => 'Bank',
    ];

    public function __construct(
        private readonly PostingService $posting,
        private readonly AuditService $audit,
        private readonly LenderService $lenders,
    ) {}

    /** @return Collection<int, array{id: int, label: string}> */
    public function paidIntoOptions(): Collection
    {
        return LedgerAccount::settlement()
            ->whereIn('name', array_keys(self::PAID_INTO_LABELS))
            ->get(['id', 'name'])
            ->sortBy(fn(LedgerAccount $account) => array_search($account->name, array_keys(self::PAID_INTO_LABELS), true))
            ->map(fn(LedgerAccount $account) => ['id' => $account->id, 'label' => self::PAID_INTO_LABELS[$account->name]])
            ->values();
    }

    /**
     * @param array{kind: string, lender: string, amount: string, date?: ?string, interest?: ?string,
     *              due_date?: ?string, note?: ?string, paid_into?: ?int, idempotency_key?: ?string,
     *              farms?: list<array{farm_unit: string, amount: string, purpose?: ?string, purpose_note?: ?string}>} $data
     */
    public function record(FarmerProfile $farmer, User $by, array $data, ?string $idempotencyKey = null, ?string $via = null): Loan
    {
        $loan = $this->validated($data);
        $parts = $this->allocations($farmer, $loan['principal_minor'], $data['farms'] ?? null);

        return DB::transaction(function () use ($farmer, $by, $loan, $parts, $data, $idempotencyKey, $via) {
            $key = $idempotencyKey ?? $data['idempotency_key'] ?? null;

            if ($key !== null && $key !== '') {
                $twin = Loan::where('farmer_profile_id', $farmer->id)->where('idempotency_key', $key)->first();

                if ($twin !== null) {
                    return $twin;
                }
            }

            $template = TransactionTemplate::where('slug', $loan['kind'] === Loan::KIND_MONEY ? 'loan_received' : 'loan_already_had')->first();

            if ($template === null) {
                throw PostingFailed::because('Loans are not set up yet.');
            }

            $transaction = $this->posting->post(new PostingRequest(
                farmerProfileId: $farmer->id,
                transactionTemplateId: $template->id,
                amount: Money::toDecimal($loan['principal_minor']),
                settlementAccountId: $loan['settlement_account_id'],
                transactionDate: $loan['loan_date'],
                narration: null,
                channel: 'web',
                recordedBy: $by->id,
                // the posting table's own key column is short, so the form's key is folded into a fixed-size one
                idempotencyKey: $key === null || $key === '' ? null : 'loan.' . md5($farmer->id . '|' . $key),
            ));

            $lender = $this->lenders->resolve($loan);

            try {
                $record = Loan::create(Arr::except($loan, self::LENDER_INPUT) + [
                    'lender_id' => $lender->id,
                    'farmer_profile_id' => $farmer->id,
                    'transaction_id' => $transaction->id,
                    'recorded_by' => $by->id,
                    'accepted_via' => $via,
                    'idempotency_key' => $key === '' ? null : $key,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw PostingFailed::because('Something went wrong. Please try again.');
            }

            foreach ($parts as $unitId => $part) {
                $record->farmAllocations()->create(['farm_unit_id' => $unitId] + $part);
            }

            $this->audit->recordOn('loan.created', $record, null, [
                'uuid' => $record->uuid,
                'kind' => $record->kind,
                'principal_minor' => $record->principal_minor,
                'transaction_id' => $record->transaction_id,
                'accepted_via' => $via,
            ], $by);

            return $record;
        });
    }

    /**
     * The farms a loan will be used on: farm unit id => amount_minor and the purpose columns. Null when none were named (agent, USSD, offline and
     * opening entries); when named they must be the farmer's own and add up exactly to the loan.
     *
     * @return array<int, array{amount_minor: int, purpose_template_id: ?int, purpose_note: ?string}>
     */
    private function allocations(FarmerProfile $farmer, int $principal, ?array $farms): array
    {
        if ($farms === null) {
            return [];
        }

        $units = $farmer->farmUnits()->pluck('id', 'uuid');
        $purposes = TransactionTemplate::loanPurposesFor($farmer)->pluck('id', 'slug');
        $parts = [];

        foreach ($farms as $row) {
            $unit = $units[$row['farm_unit'] ?? ''] ?? null;

            try {
                $minor = Money::toMinor((string) ($row['amount'] ?? ''));
            } catch (InvalidArgumentException) {
                throw PostingFailed::because(self::AMOUNT_REFUSAL);
            }

            if ($unit === null || isset($parts[$unit]) || $minor <= 0) {
                throw PostingFailed::because(self::FARMS_REFUSAL);
            }

            $parts[$unit] = ['amount_minor' => $minor] + $this->purposeOf($row, $purposes);
        }

        if ($parts === [] || array_sum(array_column($parts, 'amount_minor')) !== $principal) {
            throw PostingFailed::because(self::FARMS_REFUSAL);
        }

        return $parts;
    }

    // what one farm's money is for. A row that names none (older entries) has none; one that names a purpose must name
    // a record type the farmer can use, or "other" with a note
    private function purposeOf(array $row, \Illuminate\Support\Collection $allowed): array
    {
        $none = ['purpose_template_id' => null, 'purpose_note' => null];
        $purpose = $row['purpose'] ?? null;

        if ($purpose === null || $purpose === '') {
            return $none;
        }

        if ($purpose === self::OTHER_PURPOSE) {
            $note = trim((string) ($row['purpose_note'] ?? ''));

            return $note === '' ? throw PostingFailed::because(self::PURPOSE_NOTE_REFUSAL) : ['purpose_template_id' => null, 'purpose_note' => $note];
        }

        return isset($allowed[$purpose]) ? ['purpose_template_id' => $allowed[$purpose], 'purpose_note' => null] : throw PostingFailed::because(self::PURPOSE_REFUSAL);
    }

    public function activeFor(Transaction $original): ?Loan
    {
        return Loan::where('transaction_id', $original->id)->where('status', Loan::ACTIVE)->first();
    }

    // the loan follows its record: when the record is cancelled the loan is cancelled in the same step
    public function cancelFor(Transaction $original): ?Loan
    {
        $loan = $this->activeFor($original);

        if ($loan === null) {
            return null;
        }

        $loan->forceFill(['status' => Loan::CANCELLED])->save();

        $this->audit->recordOn('loan.cancelled', $loan, ['status' => Loan::ACTIVE], ['status' => Loan::CANCELLED]);

        return $loan;
    }

    // principal and interest still unpaid on the loans in force, in pesewas
    public function totalOwedMinor(FarmerProfile $farmer): int
    {
        return Loan::where('farmer_profile_id', $farmer->id)
            ->where('status', Loan::ACTIVE)
            ->get()
            ->sum(fn(Loan $loan) => $loan->balanceMinor());
    }

    // the loans on the farmer's dashboard banner: each with money not yet accounted for
    public function alertsFor(FarmerProfile $farmer): array
    {
        return Loan::where('farmer_profile_id', $farmer->id)->where('status', Loan::ACTIVE)->where('kind', Loan::KIND_MONEY)->orderBy('id')->get()
            ->map(fn(Loan $loan) => ['uuid' => $loan->uuid, 'unaccounted' => $loan->unaccountedMinor()])
            ->filter(fn(array $row) => $row['unaccounted'] > 0)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> the loan's own columns, every rule checked */
    public function validated(array $data): array
    {
        $kind = $data['kind'] ?? null;

        if (! in_array($kind, [Loan::KIND_MONEY, Loan::KIND_EXISTING], true)) {
            throw new InvalidArgumentException('Unknown kind of loan.');
        }

        $lender = $this->lenders->check($data);

        try {
            $principal = Money::toMinor((string) ($data['amount'] ?? ''));
        } catch (InvalidArgumentException) {
            throw PostingFailed::because(self::AMOUNT_REFUSAL);
        }

        if ($principal <= 0) {
            throw PostingFailed::because(self::AMOUNT_REFUSAL);
        }

        // an opening entry carries no date of its own: it is entered today
        $loanDate = $kind === Loan::KIND_MONEY
            ? $this->date($data['date'] ?? null)
            : Carbon::today();

        if ($loanDate->isAfter(Carbon::today())) {
            throw PostingFailed::because(self::FUTURE_REFUSAL);
        }

        $interest = ($data['interest'] ?? null) === null || trim((string) $data['interest']) === ''
            ? null
            : Money::toMinor((string) $data['interest']);

        $due = ($data['due_date'] ?? null) === null || $data['due_date'] === '' ? null : $this->date($data['due_date']);

        // an opening entry has no date received, so there is nothing to compare its due date with
        if ($due !== null && $kind === Loan::KIND_MONEY && $due->isBefore($loanDate)) {
            throw new InvalidArgumentException('The due date cannot be before the loan date.');
        }

        $paidInto = null;

        if ($kind === Loan::KIND_MONEY) {
            $paidInto = isset($data['paid_into']) ? (int) $data['paid_into'] : null;

            if ($paidInto === null || ! $this->paidIntoOptions()->contains('id', $paidInto)) {
                throw new InvalidArgumentException('The money has to go into cash, MoMo or bank.');
            }
        }

        $note = trim((string) ($data['note'] ?? ''));

        return [
            'kind' => $kind,
            ...$lender,
            'principal_minor' => $principal,
            'agreed_interest_minor' => $interest,
            'loan_date' => $loanDate->toDateString(),
            'due_date' => $due?->toDateString(),
            'note' => $note === '' ? null : $note,
            'settlement_account_id' => $paidInto,
        ];
    }

    private function date(mixed $value): Carbon
    {
        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('That date could not be read.');
        }
    }
}
