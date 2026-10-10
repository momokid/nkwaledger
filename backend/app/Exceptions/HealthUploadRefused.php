<?php

namespace App\Exceptions;

use RuntimeException;

// a refused upload step; the code is for the phone, never shown to anyone
class HealthUploadRefused extends RuntimeException
{
    public function __construct(public readonly int $status, string $code, public readonly array $extra = [])
    {
        parent::__construct($code);
    }
}
