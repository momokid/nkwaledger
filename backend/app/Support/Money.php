<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public const CURRENCY = 'GHS';

    // one hundred pesewas make a cedi
    private const SCALE = 2;

    // splits the text at the dot instead of multiplying, because 250.75 times 100 is 25074.999... in php
    public static function toMinor(string|int|float $amount): int
    {
        $text = self::clean($amount);

        if (! preg_match('/^\d*(\.\d{1,2})?$/', $text) || $text === '' || $text === '.') {
            throw new InvalidArgumentException('That amount is not a number we can read.');
        }

        [$cedis, $pesewas] = array_pad(explode('.', $text, 2), 2, '');

        $cedis = $cedis === '' ? '0' : $cedis;
        $pesewas = str_pad($pesewas, self::SCALE, '0');

        return (int) $cedis * 100 + (int) $pesewas;
    }

    public static function toDecimal(int $minor): string
    {
        return number_format($minor / 100, self::SCALE, '.', '');
    }

    public static function format(int $minor): string
    {
        return self::CURRENCY . ' ' . number_format($minor / 100, self::SCALE, '.', ',');
    }

    // strips the separators a farmer types out of habit
    private static function clean(string|int|float $amount): string
    {
        return str_replace([',', ' ', "\u{00A0}"], '', trim((string) $amount));
    }
}
