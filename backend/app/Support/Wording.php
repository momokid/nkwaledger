<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

// the one place the approved repayment and lender wording lives: resources/wording.json, also read by the React pages
class Wording
{
    public const FALLBACK = 'Something went wrong. Please try again.';

    private static ?array $map = null;

    public static function get(string $key, array $with = []): string
    {
        $text = self::map()[$key] ?? null;

        if ($text === null) {
            return self::missing($key);
        }

        // one pass, so a value that looks like {code} is never filled in again
        return $with === [] ? $text : strtr($text, array_combine(array_map(fn($name) => '{' . $name . '}', array_keys($with)), array_map('strval', $with)));
    }

    // a known key becomes its wording; text shaped like a key with no wording is a missing key; any other text is already words
    public static function resolve(string $text): string
    {
        if (isset(self::map()[$text])) {
            return self::map()[$text];
        }

        return preg_match('/^[a-z_]+(\.[a-z_]+)+$/', $text) ? self::missing($text) : $text;
    }

    // for tests only: use this map instead of the file, or null to go back to the file
    public static function swap(?array $map): void
    {
        self::$map = $map;
    }

    // local and tests stop at once; elsewhere the user sees the plain sentence and the log gets one line per key per hour
    private static function missing(string $key): string
    {
        if (app()->environment(['local', 'testing'])) {
            throw new InvalidArgumentException("No wording for {$key}");
        }

        try {
            $first = true;

            try {
                $first = Cache::add("wording-missing:{$key}", 1, now()->addHour());
            } catch (Throwable) {
            }

            if ($first) {
                Log::error('Missing wording key.', ['key' => $key, 'where' => self::caller()]);
            }
        } catch (Throwable) {
        }

        return self::FALLBACK;
    }

    private static function caller(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6) as $frame) {
            if (isset($frame['file']) && $frame['file'] !== __FILE__) {
                return $frame['file'] . ':' . ($frame['line'] ?? 0);
            }
        }

        return 'unknown';
    }

    private static function map(): array
    {
        return self::$map ??= json_decode(file_get_contents(resource_path('wording.json')), true, flags: JSON_THROW_ON_ERROR);
    }
}
