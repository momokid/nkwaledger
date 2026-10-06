<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;

// every way of signing in ends in login(), so a locked account is stopped here once for all of them
class LockAwareSessionGuard extends SessionGuard
{
    public function login(Authenticatable $user, $remember = false)
    {
        if (method_exists($user, 'isLocked') && $user->isLocked()) {
            throw AccountLockedException::make();
        }

        parent::login($user, $remember);
    }
}
