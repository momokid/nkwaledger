<?php

namespace App\Support;

class Phone
{
    // the two-digit network codes Ghana issues to mobile lines
    private const MOBILE_PREFIXES = [
        '20',
        '23',
        '24',
        '25',
        '26',
        '27',
        '28',
        '50',
        '53',
        '54',
        '55',
        '56',
        '57',
        '59',
    ];

    private const NATIONAL_LENGTH = 9;

    // every phone number entering the system passes through here, so the database only ever holds one spelling
    public static function normalise(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $cleaned = preg_replace('/[\s\-\.\(\)]/', '', $raw);

        // anything left that is not a plus or a digit means this was never a phone number
        if ($cleaned === '' || preg_match('/^\+?[0-9]+$/', $cleaned) !== 1) {
            return null;
        }

        $national = self::toNational(ltrim($cleaned, '+'));

        if ($national === null || strlen($national) !== self::NATIONAL_LENGTH) {
            return null;
        }

        if (! in_array(substr($national, 0, 2), self::MOBILE_PREFIXES, true)) {
            return null;
        }

        return '+233' . $national;
    }

    // strips whichever way the caller wrote the country code, leaving the nine digits that identify the line
    private static function toNational(string $digits): ?string
    {
        if (str_starts_with($digits, '00233')) {
            return substr($digits, 5);
        }

        if (str_starts_with($digits, '233')) {
            return substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return null;
    }
}
