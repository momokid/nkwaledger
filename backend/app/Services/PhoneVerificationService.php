<?php

namespace App\Services;

use App\Models\User;

class PhoneVerificationService
{
    // how many days before we ask again
    private const DAYS_UNTIL_NEXT = 30;

    // how many logins a normal user gets before we ask again
    private const MIN_LOGINS = 15;
    private const MAX_LOGINS = 30;

    // called once the user has proved they hold the phone
    public function markVerified(User $user): void
    {
        $user->forceFill([
            'phone_verified_at'            => now(),
            'logins_since_verification'    => 0,
            'verification_login_threshold' => $this->rollThreshold($user),
            'next_verification_at'         => now()->addDays(self::DAYS_UNTIL_NEXT),
        ])->save();
    }

    // admins verify every login, so they never need a count
    private function rollThreshold(User $user): ?int
    {
        if ($user->hasRole('admin')) {
            return null;
        }

        return random_int(self::MIN_LOGINS, self::MAX_LOGINS);
    }
}
