<?php

namespace App\Support;

use InvalidArgumentException;

// the one place the approved repayment and lender wording lives: resources/wording.json, also read by the React pages
class Wording
{
    private static ?array $map = null;

    public static function get(string $key, array $with = []): string
    {
        $text = self::map()[$key] ?? throw new InvalidArgumentException("No wording for {$key}");

        // one pass, so a value that looks like {code} is never filled in again
        return $with === [] ? $text : strtr($text, array_combine(array_map(fn($name) => '{' . $name . '}', array_keys($with)), array_map('strval', $with)));
    }

    // a known key becomes its wording; any other text is already words and is left alone
    public static function resolve(string $text): string
    {
        return self::map()[$text] ?? $text;
    }

    private static function map(): array
    {
        return self::$map ??= json_decode(file_get_contents(resource_path('wording.json')), true, flags: JSON_THROW_ON_ERROR);
    }
}
