<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

// capturing a farmer's identity document and approving it are two different jobs done by two
// different people. The rules live here rather than in a route or a page so no caller can
// skip them: the person who submitted a document is never the one who approves it
class FarmerKycService
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function submit(FarmerProfile $farmer, User $submitter, string $type, string $number): void
    {
        if (! $this->maySubmitFor($farmer, $submitter)) {
            throw new AuthorizationException('You can only submit documents for farmers assigned to you.');
        }

        // capturing is not verifying, so any earlier verification is cleared with the document
        $farmer->forceFill([
            'identity_type' => $type,
            'identity_number' => $number,
            'identity_verified_at' => null,
            'identity_verified_by' => null,
            'identity_submitted_by' => $submitter->id,
            'identity_submitted_at' => now(),
        ])->save();

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
        // approving is an admin decision, checked here as well as on the route
        if (! $approver->hasRole('admin') || ! $this->access->can($approver, 'farmers.verify')) {
            throw new AuthorizationException('Only an admin can approve an identity document.');
        }

        if ($farmer->identity_number_hash === null) {
            throw ValidationException::withMessages([
                'identity_number' => 'There is no document on this account yet. Please capture one first.',
            ]);
        }

        // holds even when the submitter also holds admin, or no longer holds the farmer
        if ($farmer->identity_submitted_by !== null && $farmer->identity_submitted_by === $approver->id) {
            throw ValidationException::withMessages([
                'identity_number' => 'Someone other than the person who submitted this document needs to approve it.',
            ]);
        }

        // whoever serves this farmer cannot also vouch for their document
        if ($farmer->conflictedUserId() === $approver->id) {
            throw ValidationException::withMessages([
                'identity_number' => 'Someone other than the person who holds this farmer needs to verify the document.',
            ]);
        }

        $farmer->forceFill([
            'identity_verified_at' => now(),
            'identity_verified_by' => $approver->id,
        ])->save();

        $this->audit->recordOn('farmer.identity_verified', $farmer);
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
}
