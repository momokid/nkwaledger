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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// the one place a loan is created, validated and posted. Loans are never edited: a mistake is cancelled
class LoanService
{
    public const AMOUNT_REFUSAL = 'Enter an amount above zero.';
    public const FUTURE_REFUSAL = 'This date is in the future.';

    // the accounts a farmer may say the money was paid into, and the words the form shows for them
    public const PAID_INTO_LABELS = [
        'Cash A/C' => 'Cash',
        'Momo A/C' => 'MoMo',
        'Bank A/C' => 'Bank',
    ];

    public function __construct(
        private readonly PostingService $posting,
        private readonly AuditService $audit,
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
     * @param array{kind: string, lender_name: string, lender_type: string, amount: string, date?: ?string, interest?: ?string,
     *              due_date?: ?string, note?: ?string, paid_into?: ?int, idempotency_key?: ?string} $data
     */
    public function record(FarmerProfile $farmer, User $by, array $data): Loan
    {
        $loan = $this->checked($data);

        return DB::transaction(function () use ($farmer, $by, $loan, $data) {
            $key = $data['idempotency_key'] ?? null;

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

            try {
                $record = Loan::create($loan + [
                    'farmer_profile_id' => $farmer->id,
                    'transaction_id' => $transaction->id,
                    'recorded_by' => $by->id,
                    'idempotency_key' => $key === '' ? null : $key,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw PostingFailed::because('Something went wrong. Please try again.');
            }

            $this->audit->recordOn('loan.created', $record, null, [
                'uuid' => $record->uuid,
                'kind' => $record->kind,
                'principal_minor' => $record->principal_minor,
                'transaction_id' => $record->transaction_id,
            ]);

            return $record;
        });
    }

    // the loan follows its record: when the record is cancelled the loan is cancelled in the same step
    public function cancelFor(Transaction $original): void
    {
        $loan = Loan::where('transaction_id', $original->id)->where('status', Loan::ACTIVE)->first();

        if ($loan === null) {
            return;
        }

        $loan->forceFill(['status' => Loan::CANCELLED])->save();

        $this->audit->recordOn('loan.cancelled', $loan, ['status' => Loan::ACTIVE], ['status' => Loan::CANCELLED]);
    }

    // principal still unpaid on the loans in force, in pesewas
    public function totalOwedMinor(FarmerProfile $farmer): int
    {
        return Loan::where('farmer_profile_id', $farmer->id)
            ->where('status', Loan::ACTIVE)
            ->get()
            ->sum(fn(Loan $loan) => $loan->balanceMinor());
    }

    /** @return array<string, mixed> the loan's own columns, every rule checked */
    private function checked(array $data): array
    {
        $kind = $data['kind'] ?? null;

        if (! in_array($kind, [Loan::KIND_MONEY, Loan::KIND_EXISTING], true)) {
            throw new InvalidArgumentException('Unknown kind of loan.');
        }

        $lender = trim((string) ($data['lender_name'] ?? ''));

        if ($lender === '' || ! in_array($data['lender_type'] ?? null, Loan::LENDER_TYPES, true)) {
            throw new InvalidArgumentException('A loan needs a lender name and a lender type.');
        }

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
            'lender_name' => $lender,
            'lender_type' => $data['lender_type'],
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
