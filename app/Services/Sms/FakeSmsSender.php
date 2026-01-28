<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class FakeSmsSender implements SmsSender
{
    public array $sentMessages = [];

    public function send(string $phoneNumber, string $message): bool
    {
        // Only for local/testing
        Log::info("FAKE SMS to {$phoneNumber}: {$message}");


        // Store messages for inspection in tests
        $this->sentMessages[] = [
            'phone' => $phoneNumber,
            'message' => $message,
        ];

        return true;
    }
}
