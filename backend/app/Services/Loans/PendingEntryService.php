<?php

namespace App\Services\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Exceptions\Loans\EntryRefused;
use App\Exceptions\Loans\RepaymentSnapshotChanged;
use App\Models\FarmerProfile;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PendingEntry;
use App\Models\PendingEntryCode;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Support\AgentLoanLink;
use App\Support\Money;
use App\Support\Wording;
use Illuminate\Support\Facades\DB;

// an agent's entry for a farmer: recorded, then accepted, declined or withdrawn. Nothing here touches the books
// until acceptance, and acceptance runs the very same LoanService checks the farmer's own form does
class PendingEntryService
{
    // provisional wording, to be approved: shown when an entry has already moved on
    public const NOT_PENDING = 'This loan is not waiting any more.';

    public const FARMER_NOTICE = 'Your agent recorded a loan for you. It is not counted until you accept it.';

    public const REPAYMENT_NOTICE = 'repayment.pending_notice';

    public function __construct(
        private readonly LoanService $loans,
        private readonly RepaymentService $repayments,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $data the same input the farmer's own form takes */
    public function recordLoan(FarmerProfile $farmer, User $agent, array $data): PendingEntry
    {
        $checked = $this->loans->validated($data);

        return DB::transaction(function () use ($farmer, $agent, $checked) {
            $entry = PendingEntry::create([
                'farmer_profile_id' => $farmer->id,
                'entry_type' => $checked['kind'] === Loan::KIND_MONEY ? PendingEntry::LOAN_MONEY : PendingEntry::LOAN_EXISTING,
                'payload' => $checked,
                'recorded_by' => $agent->id,
            ]);

            $this->audit->recordOn('pending_entry.recorded', $entry, null, [
                'uuid' => $entry->uuid,
                'entry_type' => $entry->entry_type,
                'principal_minor' => $checked['principal_minor'],
            ], $agent);

            if ($farmer->user !== null) {
                $this->notifications->send($farmer->user, 'loan.pending', self::FARMER_NOTICE, '/loans');
            }

            return $entry;
        });
    }

    /** @param array<string, mixed> $data the same input the farmer's own repayment form takes */
    public function recordRepayment(Loan $loan, User $agent, array $data): PendingEntry
    {
        if ($loan->isCancelled()) {
            throw PostingFailed::because(RepaymentService::LOAN_NOT_ACTIVE);
        }

        $checked = $this->repayments->validated($data);
        $this->repayments->mustNotPrecede($loan, $checked['date']);
        $snapshot = $this->repayments->snapshot($loan);
        $this->repayments->mustFit($loan, $checked['amount_minor']);

        return DB::transaction(function () use ($loan, $agent, $checked, $snapshot) {
            $payload = [
                'loan_uuid' => $loan->uuid,
                'amount_minor' => $checked['amount_minor'],
                'date' => $checked['date'],
                'paid_from' => $checked['paid_from'],
                'snapshot' => $snapshot,
            ];

            $entry = PendingEntry::create([
                'farmer_profile_id' => $loan->farmer_profile_id,
                'entry_type' => PendingEntry::LOAN_REPAYMENT,
                'payload' => $payload,
                'recorded_by' => $agent->id,
            ]);

            $this->audit->recordOn('pending_entry.recorded', $entry, null, [
                'uuid' => $entry->uuid,
                'entry_type' => $entry->entry_type,
                'loan_uuid' => $loan->uuid,
                'amount_minor' => $payload['amount_minor'],
            ], $agent);

            $user = $loan->farmerProfile->user;

            if ($user !== null) {
                $this->notifications->send($user, 'loan.pending', Wording::get(self::REPAYMENT_NOTICE), '/loans');
            }

            return $entry;
        });
    }

    // by either route: one lock, every check again, one loan or repayment, or nothing at all.
    // Null means a repayment was rejected because the loan is no longer as the agent saw it
    public function accept(PendingEntry $entry, User $actor, string $via): Loan|LoanRepayment|null
    {
        return DB::transaction(function () use ($entry, $actor, $via) {
            $locked = $this->lock($entry);

            if ($locked->status === PendingEntry::ACCEPTED) {
                return $locked->isRepayment() ? LoanRepayment::findOrFail($locked->result_id) : Loan::findOrFail($locked->result_id);
            }

            $this->mustBePending($locked);

            if ($locked->isRepayment()) {
                return $this->acceptRepayment($locked, $actor, $via);
            }

            $farmer = $locked->farmerProfile;
            $recorder = $locked->recordedBy ?? $actor;

            $loan = $this->loans->record($farmer, $recorder, $this->inputFrom($locked), 'entry.' . $locked->uuid, $via);

            $locked->forceFill([
                'status' => PendingEntry::ACCEPTED,
                'decided_by' => $actor->id,
                'decided_via' => $via,
                'decided_at' => now(),
                'result_type' => 'loan',
                'result_id' => $loan->id,
            ])->save();

            $this->audit->recordOn('pending_entry.accepted', $locked, ['status' => PendingEntry::PENDING], [
                'status' => PendingEntry::ACCEPTED,
                'via' => $via,
                'loan_uuid' => $loan->uuid,
            ], $actor);

            if ($via === PendingEntry::VIA_APP) {
                $this->tellAgent($locked, 'loan.accepted', 'accepted', AgentLoanLink::to($loan->uuid, $loan->uuid));
            }

            return $loan;
        });
    }

    // a cancelled loan cannot take a repayment, so every repayment still waiting on it is rejected here, in the
    // same transaction as the cancellation, and its SMS code is switched off
    public function rejectRepaymentsFor(Loan $loan, User $by): void
    {
        PendingEntry::query()
            ->where('farmer_profile_id', $loan->farmer_profile_id)
            ->where('entry_type', PendingEntry::LOAN_REPAYMENT)
            ->where('status', PendingEntry::PENDING)
            ->get()
            ->filter(fn(PendingEntry $entry) => ($entry->payload['loan_uuid'] ?? null) === $loan->uuid)
            ->each(function (PendingEntry $entry) use ($loan, $by) {
                $locked = $this->lock($entry);

                if (! $locked->isPending()) {
                    return;
                }

                $locked->forceFill([
                    'status' => PendingEntry::REJECTED,
                    'decided_by' => $by->id,
                    'decided_via' => PendingEntry::VIA_APP,
                    'decided_at' => now(),
                ])->save();

                PendingEntryCode::where('pending_entry_id', $locked->id)->whereNull('used_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);

                $this->audit->recordOn('pending_entry.rejected', $locked, ['status' => PendingEntry::PENDING], [
                    'status' => PendingEntry::REJECTED,
                    'reason' => 'loan_cancelled',
                ], $by);

                $this->tellAgent($locked, 'loan.rejected', 'rejected', AgentLoanLink::to($loan->uuid, $locked->uuid));

                if ($locked->farmerProfile->user !== null) {
                    $this->notifications->send($locked->farmerProfile->user, 'loan.rejected', Wording::get('repayment.rejected'), "/loans/{$loan->uuid}");
                }
            });
    }

    public function decline(PendingEntry $entry, User $farmerUser): void
    {
        DB::transaction(function () use ($entry, $farmerUser) {
            $locked = $this->lock($entry);
            $this->mustBePending($locked);

            $locked->forceFill([
                'status' => PendingEntry::DECLINED,
                'decided_by' => $farmerUser->id,
                'decided_via' => PendingEntry::VIA_APP,
                'decided_at' => now(),
            ])->save();

            $this->audit->recordOn('pending_entry.declined', $locked, ['status' => PendingEntry::PENDING], [
                'status' => PendingEntry::DECLINED,
                'via' => PendingEntry::VIA_APP,
            ], $farmerUser);

            $this->tellAgent($locked, 'loan.declined', 'declined', "/agent/farmers/{$locked->farmerProfile->uuid}");
        });
    }

    public function withdraw(PendingEntry $entry, User $agent): void
    {
        DB::transaction(function () use ($entry, $agent) {
            $locked = $this->lock($entry);
            $this->mustBePending($locked);

            $locked->forceFill([
                'status' => PendingEntry::WITHDRAWN,
                'decided_by' => $agent->id,
                'decided_via' => PendingEntry::VIA_APP,
                'decided_at' => now(),
            ])->save();

            $this->audit->recordOn('pending_entry.withdrawn', $locked, ['status' => PendingEntry::PENDING], [
                'status' => PendingEntry::WITHDRAWN,
            ], $agent);
        });
    }

    // two people acting at once meet here: the second waits, then sees what the first did
    public function lock(PendingEntry $entry): PendingEntry
    {
        return PendingEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
    }

    public function mustBePending(PendingEntry $entry): void
    {
        if (! $entry->isPending()) {
            throw EntryRefused::because(self::NOT_PENDING);
        }
    }

    // an agent's repayment at the moment of acceptance: the loan must still be exactly as the agent saw it
    private function acceptRepayment(PendingEntry $locked, User $actor, string $via): ?LoanRepayment
    {
        $payload = $locked->payload;

        $loan = Loan::where('uuid', $payload['loan_uuid'])->firstOrFail();

        try {
            $repayment = $this->repayments->record($loan, $locked->recordedBy ?? $actor, [
                'amount' => Money::toDecimal($payload['amount_minor']),
                'date' => $payload['date'],
                'paid_from' => $payload['paid_from'],
                'idempotency_key' => 'entry.' . $locked->uuid,
            ], $via, $payload['snapshot']);
        } catch (RepaymentSnapshotChanged) {
            $locked->forceFill([
                'status' => PendingEntry::REJECTED,
                'decided_by' => $actor->id,
                'decided_via' => $via,
                'decided_at' => now(),
            ])->save();

            $this->audit->recordOn('pending_entry.rejected', $locked, ['status' => PendingEntry::PENDING], [
                'status' => PendingEntry::REJECTED,
                'via' => $via,
            ], $actor);

            $this->tellAgent($locked, 'loan.rejected', 'rejected', AgentLoanLink::to($loan->uuid, $locked->uuid));

            return null;
        }

        $locked->forceFill([
            'status' => PendingEntry::ACCEPTED,
            'decided_by' => $actor->id,
            'decided_via' => $via,
            'decided_at' => now(),
            'result_type' => 'loan_repayment',
            'result_id' => $repayment->id,
        ])->save();

        $this->audit->recordOn('pending_entry.accepted', $locked, ['status' => PendingEntry::PENDING], [
            'status' => PendingEntry::ACCEPTED,
            'via' => $via,
            'repayment_uuid' => $repayment->uuid,
        ], $actor);

        if ($via === PendingEntry::VIA_APP) {
            $this->tellAgent($locked, 'loan.accepted', 'accepted', AgentLoanLink::to($loan->uuid, $repayment->uuid));
        }

        return $repayment;
    }

    // the stored payload back into the form's own input, so the checks that run are the same ones
    private function inputFrom(PendingEntry $entry): array
    {
        $payload = $entry->payload;

        return [
            'kind' => $payload['kind'],
            'lender' => $payload['lender'],
            'amount' => Money::toDecimal($payload['principal_minor']),
            'interest' => $payload['agreed_interest_minor'] === null ? null : Money::toDecimal($payload['agreed_interest_minor']),
            // an opening entry is dated the day it is accepted: LoanService ignores this for it
            'date' => $payload['loan_date'],
            'due_date' => $payload['due_date'],
            'note' => $payload['note'],
            'paid_into' => $payload['settlement_account_id'],
        ];
    }

    private function tellAgent(PendingEntry $entry, string $kind, string $word, string $link): void
    {
        $agent = $entry->recordedBy;
        $user = $entry->farmerProfile->user;

        if ($agent === null || $user === null) {
            return;
        }

        $name = trim("{$user->surname} {$user->first_name}");
        $message = $entry->isRepayment() ? "{$name}: " . Wording::get("repayment.{$word}") : "{$name} {$word} the loan.";

        $this->notifications->send($agent, $kind, $message, $link);
    }
}
