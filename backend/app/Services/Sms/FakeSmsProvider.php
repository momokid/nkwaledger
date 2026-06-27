<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;

class FakeSmsProvider implements SmsProvider
{
    public array $sent = [];

    public function send(string $phone, string $message): void
    {
        $this->sent[] = compact('phone', 'message');
    }

    public function sentTo(string $phone): bool
    {
        return collect($this->sent)->contains('phone', $phone);
    }
}
