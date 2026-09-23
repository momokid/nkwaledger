<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class Gs1DigitalLink
{
    // the GTIN (01) is what makes this a digital link at all; batch (10) and
    // expiry (17) are optional extras riding along with it
    private const AI_PATTERN = '/(?:\(|\/)(01|10|17)(?:\)|\/)([^\/\(]+)/';

    // pure string parsing only - this never resolves a hostname or opens a
    // connection, however url-shaped the input looks
    public static function parse(string $raw): ?array
    {
        if (! preg_match_all(self::AI_PATTERN, $raw, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $values = [];

        foreach ($matches as $match) {
            $values[$match[1]] = rtrim($match[2], '/');
        }

        if (! isset($values['01']) || ! preg_match('/^\d{14}$/', $values['01'])) {
            return null;
        }

        $expiry = null;

        if (isset($values['17']) && preg_match('/^\d{6}$/', $values['17'])) {
            $expiry = Carbon::createFromFormat('ymd', $values['17'])->startOfDay();
        }

        return [
            'gtin' => $values['01'],
            'batch' => $values['10'] ?? null,
            'expiry' => $expiry,
        ];
    }
}
