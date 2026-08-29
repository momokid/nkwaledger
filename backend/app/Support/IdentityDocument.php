<?php

namespace App\Support;

use RuntimeException;

class IdentityDocument
{
    // strips formatting so GHA-123456789-0 and gha 123456789 0 resolve to one value
    public static function normalise(string $number): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $number) ?? '');
    }

    public static function hash(string $number): string
    {
        $normalised = self::normalise($number);

        if ($normalised === '') {
            throw new RuntimeException('Identity number cannot be empty.');
        }

        return hash_hmac('sha256', $normalised, self::pepper());
    }

    public static function matches(string $number, string $hash): bool
    {
        return hash_equals($hash, self::hash($number));
    }

    private static function pepper(): string
    {
        $pepper = config('identity.pepper');

        if (blank($pepper)) {
            throw new RuntimeException('IDENTITY_PEPPER is not set.');
        }

        return $pepper;
    }
}
