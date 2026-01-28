<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TermiiSmsSender implements SmsSender
{
    public function send(string $phoneNumber, string $message): void
    {
        $response = Http::post(config('services.termii.endpoint'), [
            'to' => $phoneNumber,
            'from' => config('services.termii.sender_id'),
            'sms' => $message,
            'type' => 'plain',
            'api_key' => config('services.termii.api_key'),
            'channel' => 'generic',
        ]);

        if (! $response->successful()) {
            Log::error('Termii SMS failed', [
                'phone' => $phoneNumber,
                'response' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to send OTP SMS');
        }
    }
}
