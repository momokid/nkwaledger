<?php

namespace App\Services;

class PhoneNormalizer
{
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);

        if (str_starts_with($phone, '+233')) return $phone;
        if (str_starts_with($phone, '233'))  return '+' . $phone;
        if (str_starts_with($phone, '0'))    return '+233' . substr($phone, 1);

        return '+233' . $phone;
    }
}