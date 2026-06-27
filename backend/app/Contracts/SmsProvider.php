<?php

namespace App\Contracts;

interface SmsProvider
{
    public function send(string $phone, string $message): void;
}
