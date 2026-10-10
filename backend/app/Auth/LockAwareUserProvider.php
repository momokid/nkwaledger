<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

// a session or remember-me cookie only ever finds an account that is not locked,
// so a locked one is simply signed out on its next request, as if its session had died
class LockAwareUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier)
    {
        return $this->unlocked(parent::retrieveById($identifier));
    }

    public function retrieveByToken($identifier, $token)
    {
        return $this->unlocked(parent::retrieveByToken($identifier, $token));
    }

    private function unlocked(?Authenticatable $user): ?Authenticatable
    {
        return $user !== null && method_exists($user, 'isLocked') && $user->isLocked() ? null : $user;
    }
}
