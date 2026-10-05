<?php

namespace App\Services;

use App\Exceptions\Ledger\PostingFailed;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\SyncSubmission;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

// records made offline arrive here. Every ledger write still goes through PostingService;
// this class only decides whether a record is accepted, sent back, or held for an admin
class SyncSubmissionService
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly AccessControlService $access,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
    ) {}

    public function submit(User $user, array $record): array
    {
        $seen = $this->stored($user, $record);

        if ($seen !== null) {
            return $this->replay($seen, $record);
        }

        try {
            return $this->result(DB::transaction(fn() => $this->store($user, $record)));
        } catch (Throwable $e) {
            // an identical submission won the race to the unique uuid: answer with what it stored
            $twin = $e instanceof UniqueConstraintViolationException && str_contains($e->getMessage(), 'client_uuid')
                ? $this->stored($user, $record)
                : null;

            if ($twin !== null) {
                return $this->replay($twin, $record);
            }

            report($e);

            return ['uuid' => $record['uuid'], 'status' => 'error', 'error' => 'Something went wrong. Please try again.'];
        }
    }

    // the stored answer for a uuid already seen, unless this send carries other details
    private function replay(SyncSubmission $stored, array $record): array
    {
        $before = $stored->payload;

        return Transaction::sameDetails($before['template'], $before['amount'], $record['template'], $record['amount'])
            ? $this->result($stored)
            : ['uuid' => $record['uuid'], 'status' => 'error', 'error' => Transaction::KEY_REUSED];
    }

    // only ever this user's own row
    private function stored(User $user, array $record): ?SyncSubmission
    {
        return SyncSubmission::where('client_uuid', $record['uuid'])->where('user_id', $user->id)->first();
    }

    public function approve(SyncSubmission $submission, User $admin): SyncSubmission
    {
        return $this->review($submission, $admin, function (SyncSubmission $held) {
            // no farmer to post for: refuse with the reason the row already carries, leaving it held
            if ($held->farmer_profile_id === null) {
                throw ValidationException::withMessages(['submission' => $held->reason]);
            }

            $this->post($held);
            $this->audit->recordOn('sync.submission_approved', $held, null, ['status' => $held->status]);
        });
    }

    public function reject(SyncSubmission $submission, User $admin, string $reason): SyncSubmission
    {
        return $this->review($submission, $admin, function (SyncSubmission $held) use ($reason) {
            $held->update(['status' => SyncSubmission::REJECTED, 'reason' => $reason]);
            $this->audit->recordOn('sync.submission_rejected', $held, null, ['reason' => $reason]);
            $this->notifySubmitter($held, 'sync.rejected', "A record was not accepted. {$reason}");
        });
    }

    // what this person submitted, plus every farmer they may act for
    public function visibleTo(User $user): Builder
    {
        return SyncSubmission::query()->where(
            fn(Builder $query) => $query->where('user_id', $user->id)
                ->orWhereIn('farmer_profile_id', $this->reachableFarmerIds($user)),
        );
    }

    // an admin sees the whole platform, an agent their farmers, a farmer only themselves
    public function reachableFarmerIds(User $user): Builder
    {
        return FarmerProfile::query()->select('id')->when(
            ! $user->hasRole('admin'),
            fn(Builder $query) => $query->where(
                fn(Builder $inner) => $inner->where('assigned_agent_id', $user->id)->orWhere('user_id', $user->id),
            ),
        );
    }

    public function result(SyncSubmission $submission): array
    {
        return [
            'uuid' => $submission->client_uuid,
            'status' => $submission->status,
            'reason' => $submission->reason,
            'reference' => $submission->transaction?->reference,
        ];
    }

    private function store(User $user, array $record): SyncSubmission
    {
        // an unknown farmer is held exactly like one this user may not act for, so the two cannot be told apart
        $farmer = FarmerProfile::where('uuid', $record['farmer'])->first();
        $hold = $this->holdReason($user, $farmer);

        // only a refused submission of the same person, for the same farmer, can be fixed
        $old = isset($record['supersedes'])
            ? SyncSubmission::where('client_uuid', $record['supersedes'])
                ->where('user_id', $user->id)
                ->where('farmer_profile_id', $farmer?->id)
                ->whereIn('status', [SyncSubmission::NEEDS_FIXING, SyncSubmission::REJECTED])
                ->first()
            : null;

        $submission = SyncSubmission::create([
            'client_uuid' => $record['uuid'],
            'user_id' => $user->id,
            'farmer_profile_id' => $farmer?->id,
            'payload' => $record,
            'device_date' => $record['event_date'],
            'received_at' => now(),
            'status' => $hold === null ? SyncSubmission::ACCEPTED : SyncSubmission::HELD,
            'reason' => $hold,
            'supersedes_id' => $old?->id,
        ]);

        if ($hold !== null) {
            $this->audit->recordOn('sync.submission_held', $submission, null, ['reason' => $hold]);
            $this->notifySubmitter($submission, 'sync.held', 'A record is waiting for review.');
        } else {
            $this->post($submission);
        }

        return $submission;
    }

    private function holdReason(User $user, ?FarmerProfile $farmer): ?string
    {
        if (! $user->is_active || ! $this->access->can($user, 'transactions.create')) {
            return 'This account cannot record right now, so an admin will look at it.';
        }

        if ($farmer === null || (! $user->hasRole('admin') && $farmer->user_id !== $user->id && $farmer->assigned_agent_id !== $user->id)) {
            return 'This farmer is not one you record for, so an admin will look at it.';
        }

        return null;
    }

    // posts through the ledger; a refusal sends the record back, nothing is written
    private function post(SyncSubmission $submission): void
    {
        try {
            $this->assertMayUse($submission);
            $transaction = $this->posting->post($this->postingRequest($submission));
        } catch (PostingFailed $failure) {
            // not the record's fault: undo this record's work and let a retry post it
            if ($failure->isSystem()) {
                throw $failure;
            }

            $submission->update(['status' => SyncSubmission::NEEDS_FIXING, 'reason' => $failure->getMessage()]);
            $this->notifySubmitter($submission, 'sync.needs_fixing', "A record could not be saved. {$submission->reason}");

            return;
        }

        $submission->update(['status' => SyncSubmission::ACCEPTED, 'reason' => null, 'transaction_id' => $transaction->id]);

        if ($submission->supersedes_id !== null) {
            SyncSubmission::whereKey($submission->supersedes_id)->update(['status' => SyncSubmission::SUPERSEDED]);
        }
    }

    // the same limits the web form puts on what a farmer may pick
    private function assertMayUse(SyncSubmission $submission): void
    {
        $record = $submission->payload;
        $refusal = TransactionTemplate::refusalFor($record['template'], $submission->farmerProfile);

        if ($refusal !== null) {
            throw PostingFailed::because($refusal);
        }

        if ($record['is_credit'] ?? false) {
            $refusal = TransactionTemplate::find($record['template'])->creditRefusal();

            if ($refusal !== null) {
                throw PostingFailed::because($refusal);
            }

            return;
        }

        $account = $record['settlement_account_id'] ?? null;

        if ($account !== null && ! LedgerAccount::isPickableForSettlement($account)) {
            throw PostingFailed::because(LedgerAccount::NOT_PICKABLE);
        }
    }

    private function postingRequest(SyncSubmission $submission): PostingRequest
    {
        $record = $submission->payload;
        $template = TransactionTemplate::find($record['template']);
        $quantity = $record['quantity'] ?? null;

        return new PostingRequest(
            farmerProfileId: $submission->farmer_profile_id,
            transactionTemplateId: (int) $record['template'],
            amount: $record['amount'],
            settlementAccountId: ($record['is_credit'] ?? false) && $template !== null
                ? $template->creditSettlementAccountId()
                : (isset($record['settlement_account_id']) ? (int) $record['settlement_account_id'] : null),
            transactionDate: $record['event_date'],
            farmUnitId: isset($record['farm_unit_id']) ? (int) $record['farm_unit_id'] : null,
            narration: $record['narration'] ?? null,
            recordedBy: $submission->user_id,
            // per user, so one person's key can never match another's
            idempotencyKey: "sync.{$submission->user_id}.{$submission->client_uuid}",
            quantityLost: $template?->transaction_type === Transaction::LOSS ? $quantity : null,
            quantitySold: $template?->is_produce_sale ? $quantity : null,
            quantityPurchased: $template?->is_stock_purchase ? $quantity : null,
        );
    }

    // the person who sent it, and the farmer's agent when the farmer sent it themselves
    private function notifySubmitter(SyncSubmission $submission, string $kind, string $message): void
    {
        $farmer = $submission->farmerProfile;
        $recipients = collect([$submission->user]);

        if ($farmer !== null && $submission->user_id === $farmer->user_id && $farmer->assignedAgent !== null) {
            $recipients->push($farmer->assignedAgent);
        }

        $recipients->unique('id')->each(fn(User $user) => $this->notifications->send($user, $kind, $message));
    }

    // locked and re-read, so two admins acting at once cannot both decide
    private function review(SyncSubmission $submission, User $admin, Closure $decide): SyncSubmission
    {
        if (! $admin->hasRole('admin')) {
            throw new AuthorizationException('Only an admin can review a held record.');
        }

        return DB::transaction(function () use ($submission, $admin, $decide) {
            $held = SyncSubmission::lockForUpdate()->findOrFail($submission->id);

            if ($held->status !== SyncSubmission::HELD) {
                throw ValidationException::withMessages(['submission' => 'This record has already been decided.']);
            }

            $held->update(['reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            $decide($held);

            return $held;
        });
    }
}
