<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\User;
use App\Support\PhotoUpload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

// capturing a farmer's identity document and approving it are two different jobs done by two
// different people. The rules live here rather than in a route or a page so no caller can
// skip them: the person who submitted a document is never the one who approves or rejects it
class FarmerKycService
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function submit(FarmerProfile $farmer, User $submitter, string $type, string $number, UploadedFile $photo): void
    {
        if (! $this->maySubmitFor($farmer, $submitter)) {
            throw new AuthorizationException('You can only submit documents for farmers assigned to you.');
        }

        $oldPhoto = $farmer->identity_photo_path;
        $newPhoto = PhotoUpload::store($photo, 'kyc', config('filesystems.photo_disk'));

        // capturing is not verifying, so any earlier verification or rejection is cleared with the document
        try {
            $farmer->forceFill([
                'identity_type' => $type,
                'identity_number' => $number,
                'identity_photo_path' => $newPhoto,
                'identity_verified_at' => null,
                'identity_verified_by' => null,
                'identity_rejected_reason' => null,
                'identity_rejected_by' => null,
                'identity_rejected_at' => null,
                'identity_submitted_by' => $submitter->id,
                'identity_submitted_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Storage::disk(config('filesystems.photo_disk'))->delete($newPhoto);

            throw $e;
        }

        // only once the new one is safely saved does the old one go
        if ($oldPhoto !== null) {
            Storage::disk(config('filesystems.photo_disk'))->delete($oldPhoto);
        }

        $this->audit->recordOn('farmer.identity_captured', $farmer);

        $this->notifications->sendToPermission(
            permission: 'farmers.verify',
            kind: 'farmer.kyc_submitted',
            message: 'An identity document is waiting for approval.',
            linkFor: fn(User $recipient) => $recipient->hasRole('admin') ? '/admin/approvals' : null,
            except: $submitter,
        );
    }

    public function approve(FarmerProfile $farmer, User $approver): void
    {
        $this->guardReviewable($farmer, $approver);

        if ($farmer->identity_photo_path === null) {
            throw ValidationException::withMessages(['photo' => 'Please add a photo of the farmer.']);
        }

        $farmer->forceFill([
            'identity_verified_at' => now(),
            'identity_verified_by' => $approver->id,
        ])->save();

        $this->audit->recordOn('farmer.identity_verified', $farmer);
    }

    public function reject(FarmerProfile $farmer, User $rejecter, string $reason): void
    {
        $this->guardReviewable($farmer, $rejecter);

        if ($farmer->identity_verified_at !== null) {
            $this->throwNoDocument();
        }

        $farmer->forceFill([
            'identity_rejected_reason' => $reason,
            'identity_rejected_by' => $rejecter->id,
            'identity_rejected_at' => now(),
        ])->save();

        $this->audit->recordOn('farmer.identity_rejected', $farmer, null, ['reason' => $reason]);

        $name = trim("{$farmer->user?->surname} {$farmer->user?->first_name}");
        $message = "ID verification rejected for {$name}. Reason: {$reason}.";
        $agent = $farmer->assignedAgent;

        if ($agent !== null) {
            $this->notifications->send($agent, 'farmer.kyc_rejected', $message, "/agent/farmers/{$farmer->uuid}");

            return;
        }

        // nobody holds the farmer: the other admins get the link, the submitter only the words
        $submitter = $farmer->identitySubmittedBy;

        User::role('admin')
            ->whereNotIn('id', array_filter([$rejecter->id, $submitter?->id]))
            ->get()
            ->each(fn(User $admin) => $this->notifications->send($admin, 'farmer.kyc_rejected', $message, "/admin/farmers/{$farmer->uuid}"));

        if ($submitter !== null) {
            $this->notifications->send($submitter, 'farmer.kyc_rejected', $message);
        }
    }

    // an admin captures for any farmer, an agent only for a farmer assigned to them
    public function maySubmitFor(FarmerProfile $farmer, User $submitter): bool
    {
        if ($submitter->hasRole('admin')) {
            return $this->access->can($submitter, 'farmers.update');
        }

        return $this->access->can($submitter, 'farmers.kyc-submit')
            && $farmer->assigned_agent_id !== null
            && $farmer->assigned_agent_id === $submitter->id;
    }

    // the agent who holds the farmer, or an admin
    public function mayViewPhotoOf(FarmerProfile $farmer, User $viewer): bool
    {
        return $this->access->can($viewer, 'farmers.view')
            && ($viewer->hasRole('admin')
                || ($farmer->assigned_agent_id !== null && $farmer->assigned_agent_id === $viewer->id));
    }

    // the rules approving and rejecting share: an admin, on a submission still waiting,
    // who is neither its submitter nor whoever holds the farmer
    private function guardReviewable(FarmerProfile $farmer, User $reviewer): void
    {
        if (! $reviewer->hasRole('admin') || ! $this->access->can($reviewer, 'farmers.verify')) {
            throw new AuthorizationException('Only an admin can review an identity document.');
        }

        if ($farmer->identity_number_hash === null || $farmer->identity_rejected_at !== null) {
            $this->throwNoDocument();
        }

        // holds even when the submitter also holds admin, or no longer holds the farmer
        if ($farmer->identity_submitted_by !== null && $farmer->identity_submitted_by === $reviewer->id) {
            throw ValidationException::withMessages([
                'identity_number' => 'Someone other than the person who submitted this document needs to approve it.',
            ]);
        }

        // whoever serves this farmer cannot also vouch for their document
        if ($farmer->conflictedUserId() === $reviewer->id) {
            throw ValidationException::withMessages([
                'identity_number' => 'Someone other than the person who holds this farmer needs to verify the document.',
            ]);
        }
    }

    private function throwNoDocument(): never
    {
        throw ValidationException::withMessages([
            'identity_number' => 'There is no document on this account yet. Please capture one first.',
        ]);
    }
}
