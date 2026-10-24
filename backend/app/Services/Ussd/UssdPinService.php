<?php

namespace App\Services\Ussd;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// the farmer's USSD PIN lives on the server, hashed. Five wrong tries lock it for 30 minutes, then it opens by itself
class UssdPinService
{
    public const MAX_TRIES = 5;
    public const LOCK_MINUTES = 30;

    // four digits, not all the same, not a straight run up or down
    public function isAllowed(string $pin): bool
    {
        if (preg_match('/^\d{4}$/', $pin) !== 1) {
            return false;
        }

        $digits = array_map('intval', str_split($pin));
        $steps = array_unique(array_map(fn($i) => $digits[$i] - $digits[$i - 1], [1, 2, 3]));

        return ! (count($steps) === 1 && in_array($steps[0], [0, 1, -1], true));
    }

    public function isLocked(User $user): bool
    {
        return $user->ussd_pin_locked_at !== null && $user->ussd_pin_locked_at->gt(now()->subMinutes(self::LOCK_MINUTES));
    }

    public function hashOf(string $pin): string
    {
        return Hash::make($pin);
    }

    public function matches(string $pin, string $hash): bool
    {
        return Hash::check($pin, $hash);
    }

    public function save(User $user, string $hash): void
    {
        $user->forceFill(['ussd_pin_hash' => $hash, 'ussd_pin_attempts' => 0, 'ussd_pin_locked_at' => null, 'ussd_pin_must_change' => false])->save();
    }

    // a PIN the agent hands over after checking who is asking: it opens the door once, to a forced change
    public function setTemporary(User $user, string $pin): void
    {
        $user->forceFill(['ussd_pin_hash' => $this->hashOf($pin), 'ussd_pin_attempts' => 0, 'ussd_pin_locked_at' => null, 'ussd_pin_must_change' => true])->save();
    }

    // true for the right PIN; a wrong one is counted and may lock
    public function check(User $user, string $pin): bool
    {
        if ($user->ussd_pin_locked_at !== null && ! $this->isLocked($user)) {
            $user->forceFill(['ussd_pin_attempts' => 0, 'ussd_pin_locked_at' => null]);
        }

        if ($this->matches($pin, (string) $user->ussd_pin_hash)) {
            $user->forceFill(['ussd_pin_attempts' => 0])->save();

            return true;
        }

        $tries = $user->ussd_pin_attempts + 1;
        $user->forceFill(['ussd_pin_attempts' => $tries, 'ussd_pin_locked_at' => $tries >= self::MAX_TRIES ? now() : null])->save();

        return false;
    }
}
