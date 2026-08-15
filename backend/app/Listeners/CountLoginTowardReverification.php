<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class CountLoginTowardReverification
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        // admins prove their phone every login, so no count applies
        if ($user->hasRole('admin')) {
            return;
        }

        // already unverified, the gate has them
        if ($user->phone_verified_at === null) {
            return;
        }

        // no cycle started yet
        if ($user->verification_login_threshold === null) {
            return;
        }

        $count = $user->logins_since_verification + 1;

        $user->forceFill([
            'logins_since_verification' => $count,
            // the deadline stays put so we can tell overdue from never verified
            'phone_verified_at' => $count >= $user->verification_login_threshold
                ? null
                : $user->phone_verified_at,
        ])->save();
    }
}
