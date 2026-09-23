<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Log;

class LogSmsProvider implements SmsProvider
{
    public function send(string $phone, string $message): void
    {
        Log::channel('sms')->info("SMS to {$phone}: {$message}");
    }
}
