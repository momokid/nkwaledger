<?php

namespace App\Support;

class CsvCell
{
    public static function text(?string $value): ?string
    {
        $first = ltrim($value ?? '', ' ')[0] ?? '';

        return $first !== '' && str_contains("=+-@\t\r", $first) ? "'" . $value : $value;
    }
}
