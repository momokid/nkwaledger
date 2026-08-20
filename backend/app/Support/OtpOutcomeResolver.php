<?php

namespace App\Support;

use App\Models\User;
use App\Services\OtpService;
use InvalidArgumentException;

class OtpOutcomeResolver
{
    // what a verified code of each type means, in one place
    private const OUTCOMES = [
        'registration'       => ['authenticates' => true,  'verifies' => false],
        'login'              => ['authenticates' => true,  'verifies' => true],
        'password_reset'     => ['authenticates' => false, 'verifies' => false],
        'phone_verification' => ['authenticates' => false, 'verifies' => true],
        'invitation'         => ['authenticates' => false, 'verifies' => false],
    ];

    // types that go somewhere other than a dashboard once the code checks out
    private const DESTINATIONS = [
        'invitation' => '/set-password',
    ];

    public function __construct(private readonly DashboardRouteResolver $dashboard) {}

    // says whether a session should exist after this code
    public function authenticates(string $type): bool
    {
        return $this->outcome($type)['authenticates'];
    }

    // says whether holding this code proves the person holds the phone
    public function verifiesPhone(string $type): bool
    {
        return $this->outcome($type)['verifies'];
    }

    // says where they land once the code checks out
    public function path(string $type, ?User $user): string
    {
        $this->outcome($type);

        return self::DESTINATIONS[$type] ?? $this->dashboard->path($user);
    }

    // an unlisted type means someone added an otp reason without deciding what it does
    private function outcome(string $type): array
    {
        if (! array_key_exists($type, self::OUTCOMES)) {
            throw new InvalidArgumentException("No outcome defined for OTP type [{$type}].");
        }

        return self::OUTCOMES[$type];
    }
}
