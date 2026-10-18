<?php

namespace App\Services\Ussd;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;

// one reply per call. The step the farmer is on is kept in the cache for the length of the session, and nothing typed is logged
class UssdFlow
{
    private const SESSION_MINUTES = 5;

    public function __construct(
        private readonly UssdPinService $pins,
        private readonly UssdText $text,
        private readonly UssdReads $reads,
    ) {}

    /** @return array{message: string, continue: bool} */
    public function handle(string $session, string $msisdn, bool $new, string $input): array
    {
        $key = 'ussd:' . $session;
        $state = $new ? null : Cache::get($key);
        $user = $state === null ? $this->farmerFor($msisdn) : User::find($state['user']);

        // a number that is not a farmer's, a different number from the one the session began with, or a block, all read the same
        if ($user === null || ($state !== null && Phone::normalise($msisdn) !== $user->phone)) {
            Cache::forget($key);

            return $this->end('not_registered');
        }

        if ($state === null) {
            if ($this->pins->isLocked($user)) {
                return $this->end('pin_locked');
            }

            $step = $user->ussd_pin_hash === null ? 'set_pin' : 'enter_pin';

            return $this->show($key, ['user' => $user->id, 'step' => $step], $step);
        }

        return match ($state['step']) {
            'set_pin' => $this->pins->isAllowed($input)
                ? $this->show($key, [...$state, 'step' => 'confirm_pin', 'hash' => $this->pins->hashOf($input)], 'confirm_pin')
                : $this->show($key, $state, 'pin_rule'),
            'confirm_pin' => $this->confirm($key, $state, $user, $input),
            'enter_pin' => $this->enter($key, $state, $user, $input),
            'period' => $this->reads->isPeriod($input)
                ? $this->end(null, $key, $this->reads->summary($user->farmerProfile, $input))
                : $this->show($key, $state, 'period'),
            default => $this->choose($key, $state, $user, $input),
        };
    }

    private function choose(string $key, array $state, User $user, string $input): array
    {
        return match ($input) {
            '1' => $this->show($key, [...$state, 'step' => 'period'], 'period'),
            '2' => $this->end(null, $key, $this->reads->loans($user->farmerProfile)),
            '6' => $this->end('goodbye', $key),
            default => $this->menu($key, $state, $user),
        };
    }

    private function confirm(string $key, array $state, User $user, string $input): array
    {
        if (! $this->pins->matches($input, $state['hash'])) {
            return $this->show($key, ['user' => $user->id, 'step' => 'set_pin'], 'pin_mismatch');
        }

        $this->pins->save($user, $state['hash']);

        return $this->menu($key, ['user' => $user->id], $user);
    }

    private function enter(string $key, array $state, User $user, string $input): array
    {
        if ($this->pins->isLocked($user)) {
            return $this->end('pin_locked', $key);
        }

        if ($this->pins->check($user, $input)) {
            return $this->menu($key, $state, $user);
        }

        return $this->pins->isLocked($user) ? $this->end('pin_locked', $key) : $this->show($key, $state, 'wrong_pin');
    }

    private function menu(string $key, array $state, User $user): array
    {
        return $this->show($key, ['user' => $user->id, 'step' => 'menu'], 'menu', ['name' => $user->first_name]);
    }

    private function show(string $key, array $state, string $screen, array $with = []): array
    {
        Cache::put($key, $state, now()->addMinutes(self::SESSION_MINUTES));

        return ['message' => $this->text->get($screen, $with), 'continue' => true];
    }

    private function end(?string $screen, ?string $key = null, ?string $message = null): array
    {
        if ($key !== null) {
            Cache::forget($key);
        }

        return ['message' => $message ?? $this->text->get($screen), 'continue' => false];
    }

    // only an active farmer with a verified number gets in
    private function farmerFor(string $msisdn): ?User
    {
        $phone = Phone::normalise($msisdn);

        if ($phone === null) {
            return null;
        }

        return User::query()
            ->where('phone', $phone)
            ->where('is_active', true)
            ->whereNull('locked_at')
            ->whereNotNull('phone_verified_at')
            ->whereHas('farmerProfile')
            ->role('farmer')
            ->first();
    }
}
