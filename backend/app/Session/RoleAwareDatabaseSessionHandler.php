<?php

namespace App\Session;

use App\Models\User;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Carbon;

// Laravel's session lifetime is one static value per request; this resolves it
// per session row instead, so farmers/agents get a longer lifetime than everyone else
class RoleAwareDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function expired($session)
    {
        if (! isset($session->last_activity)) {
            return false;
        }

        return $session->last_activity < Carbon::now()->subMinutes($this->minutesFor($session))->getTimestamp();
    }

    public function gc($lifetime): int
    {
        $extendedLifetime = $this->extendedMinutes() * 60;

        return $this->getQuery()
            ->where('last_activity', '<=', $this->currentTime() - $lifetime)
            ->where(function ($query) use ($extendedLifetime) {
                $query->whereNotIn('user_id', $this->extendedRoleUserIds())
                    ->orWhere('last_activity', '<=', $this->currentTime() - $extendedLifetime);
            })
            ->delete();
    }

    protected function minutesFor($session): int
    {
        $userId = $session->user_id ?? null;

        if (! $userId) {
            return $this->minutes;
        }

        $user = User::find($userId);

        return $user && $user->hasAnyRole(config('session.extended_lifetime_roles', []))
            ? $this->extendedMinutes()
            : $this->minutes;
    }

    protected function extendedRoleUserIds()
    {
        return User::whereHas(
            'roles',
            fn($query) => $query->whereIn('name', config('session.extended_lifetime_roles', [])),
        )->pluck('id');
    }

    protected function extendedMinutes(): int
    {
        return (int) config('session.extended_lifetime', $this->minutes);
    }
}
