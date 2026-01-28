<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class FakeSmsSender implements SmsSender
{
    public function send(string $phoneNumber, string $message): void
    {
        // Only for local/testing
        Log::info("FAKE SMS to {$phoneNumber}: {$message}");
    }
}
