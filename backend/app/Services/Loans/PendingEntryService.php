<?php

namespace App\Services\Loans;

use App\Exceptions\Loans\EntryRefused;
use App\Models\FarmerProfile;
use App\Models\Loan;
use App\Models\PendingEntry;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

// an agent's entry for a farmer: recorded, then accepted, declined or withdrawn. Nothing here touches the books
// until acceptance, and acceptance runs the very same LoanService checks the farmer's own form does
class PendingEntryService
{
    // provisional wording, to be approved: shown when an entry has already moved on
    public const NOT_PENDING = 'This loan is not waiting any more.';

    public const FARMER_NOTICE = 'Your agent recorded a loan for you. It is not counted until you accept it.';

    public function __construct(
        private readonly LoanService $loans,
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

    // by either route: one lock, every check again, one loan, or nothing at all
    public function accept(PendingEntry $entry, User $actor, string $via): Loan
    {
        return DB::transaction(function () use ($entry, $actor, $via) {
            $locked = $this->lock($entry);

            if ($locked->status === PendingEntry::ACCEPTED) {
                return Loan::findOrFail($locked->result_id);
            }

            $this->mustBePending($locked);

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
                $this->tellAgent($locked, 'loan.accepted', 'accepted');
            }

            return $loan;
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

            $this->tellAgent($locked, 'loan.declined', 'declined');
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

    // the stored payload back into the form's own input, so the checks that run are the same ones
    private function inputFrom(PendingEntry $entry): array
    {
        $payload = $entry->payload;

        return [
            'kind' => $payload['kind'],
            'lender_name' => $payload['lender_name'],
            'lender_type' => $payload['lender_type'],
            'amount' => Money::toDecimal($payload['principal_minor']),
            'interest' => $payload['agreed_interest_minor'] === null ? null : Money::toDecimal($payload['agreed_interest_minor']),
            // an opening entry is dated the day it is accepted: LoanService ignores this for it
            'date' => $payload['loan_date'],
            'due_date' => $payload['due_date'],
            'note' => $payload['note'],
            'paid_into' => $payload['settlement_account_id'],
        ];
    }

    private function tellAgent(PendingEntry $entry, string $kind, string $word): void
    {
        $agent = $entry->recordedBy;
        $user = $entry->farmerProfile->user;

        if ($agent === null || $user === null) {
            return;
        }

        $this->notifications->send($agent, $kind, trim("{$user->surname} {$user->first_name}") . " {$word} the loan.", "/agent/farmers/{$entry->farmerProfile->uuid}");
    }
}
