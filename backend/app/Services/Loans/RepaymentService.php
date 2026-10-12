<?php

namespace App\Services\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Exceptions\Loans\RepaymentSnapshotChanged;
use App\Models\Loan;
use App\Models\LoanRepayment;
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

// the one place a money repayment is split, validated and posted. One amount in; a LOAN record for the principal
// part and an EXPENSE record for the interest part out, both or neither. Repayments are never edited
class RepaymentService
{
    public const EXCESS_PROMPT = 'This is more than you still owe on this loan. Is the extra amount interest or fees?';

    // placeholders: the wording for these is not approved yet
    public const EXCESS_NOT_CONFIRMED = 'repayment.excess_not_confirmed';
    public const CONFIRMATION_NOT_NEEDED = 'repayment.confirmation_not_needed';
    public const LOAN_NOT_ACTIVE = 'repayment.loan_not_active';
    public const PAID_FROM_INVALID = 'repayment.paid_from_invalid';
    public const CODE_NOT_ALLOWED = 'repayment.code_not_allowed';

    public function __construct(
        private readonly PostingService $posting,
        private readonly AuditService $audit,
        private readonly LoanService $loans,
    ) {}

    /** @return array<string, mixed> what a page shows of a loan: public ids only */
    public function summary(Loan $loan): array
    {
        return [
            'uuid' => $loan->uuid,
            'lender' => $loan->lender_name,
            'amount' => $loan->principal_minor,
            'agreed_interest' => $loan->agreed_interest_minor,
            'balance' => $loan->balanceMinor(),
            'interest_remaining' => $loan->isCancelled() ? 0 : $loan->remainingInterestMinor(),
            'status' => $loan->displayStatus(),
        ];
    }

    /** @return Collection<int, array{value: string, label: string}> what the form offers: no row id leaves the server */
    public function paidFromOptions(): Collection
    {
        return $this->loans->paidIntoOptions()->map(fn(array $option) => ['value' => strtolower($option['label']), 'label' => $option['label']])->values();
    }

    // the account behind a form value like cash, momo or bank
    public function accountIdFor(?string $value): ?int
    {
        $option = $this->loans->paidIntoOptions()->first(fn(array $option) => strtolower($option['label']) === $value);

        return $option['id'] ?? null;
    }

    /** @return array{remaining_principal: int, remaining_interest: int} what is still owed, from the books */
    public function snapshot(Loan $loan): array
    {
        return [
            'remaining_principal' => $loan->remainingPrincipalMinor(),
            'remaining_interest' => $loan->remainingInterestMinor(),
        ];
    }

    /** @return array{principal: int, interest: int, excess: int, remaining_principal: int, remaining_interest: int} */
    public function preview(Loan $loan, string $amount): array
    {
        return $this->split($loan, $this->amountMinor($amount));
    }

    /** @return array{amount_minor: int, date: string, paid_from: int} every rule that needs no lock */
    public function validated(array $data): array
    {
        $date = $this->date($data['date'] ?? null);

        if ($date->isAfter(Carbon::today())) {
            throw PostingFailed::because(LoanService::FUTURE_REFUSAL);
        }

        $paidFrom = isset($data['paid_from']) ? (int) $data['paid_from'] : 0;

        if (! $this->loans->paidIntoOptions()->contains('id', $paidFrom)) {
            throw PostingFailed::because(self::PAID_FROM_INVALID);
        }

        return [
            'amount_minor' => $this->amountMinor((string) ($data['amount'] ?? '')),
            'date' => $date->toDateString(),
            'paid_from' => $paidFrom,
        ];
    }

    /**
     * @param array{amount: string, date: string, paid_from: int, confirm_excess?: bool, idempotency_key?: ?string} $data
     * @param array{remaining_principal: int, remaining_interest: int}|null $snapshot an agent's entry: the loan must still be exactly this
     */
    public function record(Loan $loan, User $by, array $data, ?string $via = null, ?array $snapshot = null): LoanRepayment
    {
        $checked = $this->validated($data);
        $key = ($data['idempotency_key'] ?? '') === '' ? null : $data['idempotency_key'];

        return DB::transaction(function () use ($loan, $by, $data, $checked, $key, $via, $snapshot) {
            // whoever comes second waits here, then sees what the first one wrote
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($key !== null) {
                $twin = LoanRepayment::where('farmer_profile_id', $locked->farmer_profile_id)->where('idempotency_key', $key)->first();

                if ($twin !== null) {
                    return $twin;
                }
            }

            if ($locked->isCancelled()) {
                throw PostingFailed::because(self::LOAN_NOT_ACTIVE);
            }

            if ($snapshot !== null && $snapshot != $this->snapshot($locked)) {
                throw new RepaymentSnapshotChanged();
            }

            $split = $this->split($locked, $checked['amount_minor']);
            $confirmed = (bool) ($data['confirm_excess'] ?? false);

            if ($split['excess'] > 0 && ! $confirmed) {
                throw PostingFailed::because(self::EXCESS_NOT_CONFIRMED);
            }

            if ($split['excess'] === 0 && $confirmed) {
                throw PostingFailed::because(self::CONFIRMATION_NOT_NEEDED);
            }

            $principal = $split['principal'] > 0 ? $this->post('loan_repayment_principal', $locked, $by, $checked, $split['principal'], 'rp', $key) : null;
            $interest = $split['interest'] > 0 ? $this->post('loan_repayment_interest', $locked, $by, $checked, $split['interest'], 'ri', $key) : null;

            try {
                $repayment = LoanRepayment::create([
                    'loan_id' => $locked->id,
                    'farmer_profile_id' => $locked->farmer_profile_id,
                    'amount_minor' => $checked['amount_minor'],
                    'principal_minor' => $split['principal'],
                    'interest_minor' => $split['interest'],
                    'principal_transaction_id' => $principal?->id,
                    'interest_transaction_id' => $interest?->id,
                    'paid_from_account_id' => $checked['paid_from'],
                    'repaid_on' => $checked['date'],
                    'excess_confirmed' => $split['excess'] > 0,
                    'recorded_by' => $by->id,
                    'accepted_via' => $via,
                    'idempotency_key' => $key,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw PostingFailed::because('Something went wrong. Please try again.');
            }

            $this->audit->recordOn('loan_repayment.created', $repayment, null, [
                'uuid' => $repayment->uuid,
                'loan_uuid' => $locked->uuid,
                'amount_minor' => $repayment->amount_minor,
                'principal_minor' => $repayment->principal_minor,
                'interest_minor' => $repayment->interest_minor,
                'excess_confirmed' => $repayment->excess_confirmed,
                'accepted_via' => $via,
            ], $by);

            return $repayment;
        });
    }

    // the split from what the books hold now; the client never supplies any of it
    private function split(Loan $loan, int $amount): array
    {
        return RepaymentSplit::compute(
            $amount,
            $loan->principal_minor,
            (int) $loan->agreed_interest_minor,
            $loan->principalRepaidMinor(),
            $loan->interestPaidMinor(),
        );
    }

    private function post(string $slug, Loan $loan, User $by, array $checked, int $minor, string $prefix, ?string $key): Transaction
    {
        $template = TransactionTemplate::where('slug', $slug)->first();

        if ($template === null) {
            throw PostingFailed::because('Loans are not set up yet.');
        }

        return $this->posting->post(new PostingRequest(
            farmerProfileId: $loan->farmer_profile_id,
            transactionTemplateId: $template->id,
            amount: Money::toDecimal($minor),
            settlementAccountId: $checked['paid_from'],
            transactionDate: $checked['date'],
            channel: 'web',
            recordedBy: $by->id,
            idempotencyKey: $key === null ? null : "{$prefix}." . md5($loan->farmer_profile_id . '|' . $key),
        ));
    }

    private function amountMinor(string $amount): int
    {
        try {
            $minor = Money::toMinor($amount);
        } catch (InvalidArgumentException) {
            throw PostingFailed::because(LoanService::AMOUNT_REFUSAL);
        }

        if ($minor <= 0) {
            throw PostingFailed::because(LoanService::AMOUNT_REFUSAL);
        }

        return $minor;
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
