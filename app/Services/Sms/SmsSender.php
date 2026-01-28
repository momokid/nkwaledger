<?php

namespace App\Services\Sms;

interface SmsSender
{
    public function send(string $phoneNumber, string $message): bool;
}
