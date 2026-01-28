<?php

namespace App\Services\Auth;

use InvalidArgumentException;

class PhoneNumberNormalizer
{

    public function normalize(string $phoneNumber): string
    {
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);

        if (str_starts_with($phoneNumber, '+233')) {
            $phoneNumber = substr($phoneNumber, 1);
        }

        if (str_starts_with($phoneNumber, '0233')) {
            $phoneNumber = substr($phoneNumber, 1);
        }

        if (preg_match('/^0\d{9}$/', $phoneNumber)) {
            $phoneNumber = '233' . substr($phoneNumber, 1);
        }

        if (! preg_match('/^233\d{9}$/', $phoneNumber)) {
            throw new InvalidArgumentException('Invalid Ghana phone number.');
        }

        return $phoneNumber;
    }
}
