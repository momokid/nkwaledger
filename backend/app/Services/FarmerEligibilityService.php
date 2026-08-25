<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\User;

class FarmerEligibilityService
{
    public function canTransact(User $user): bool
    {
        return $this->reasonCannotTransact($user) === null;
    }

    // the identity document gates credit and bank facing reports, never daily recording
    public function canBeCreditScored(User $user): bool
    {
        return $this->canTransact($user)
            && $user->farmerProfile?->identity_verified_at !== null;
    }

    // one wording for web, mobile and USSD, returns null when nothing is blocking
    public function reasonCannotTransact(User $user): ?string
    {
        if (! $user->hasRole('farmer')) {
            return 'This account is not set up for recording farm transactions.';
        }

        if (! $user->is_active) {
            return 'Your account is on hold at the moment. Please speak to your agent.';
        }

        $profile = $user->farmerProfile;

        if (! $profile instanceof FarmerProfile) {
            return 'Let us finish setting up your farm profile, then you can start recording.';
        }

        if ($user->phone_verified_at === null) {
            return 'Please confirm your phone number, then you can start recording.';
        }

        if (! $profile->is_active) {
            return 'Your farm profile is on hold at the moment. Please speak to your agent.';
        }

        if (! $profile->farmTypes()->exists()) {
            return 'Tell us what you farm and you can start recording right away.';
        }

        return null;
    }
}
