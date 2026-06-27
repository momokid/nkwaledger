<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ArkeselSmsProvider implements SmsProvider
{
    public function __construct(private readonly string $apiKey, private readonly string $sender) {}

    public function send(string $phone, string $message): void
    {
        $response = Http::post('https://sms.arkesel.com/api/v2/sms/send', [
            'sender'     => $this->sender,
            'message'    => $message,
            'recipients' => [$phone],
        ])->withHeaders(['api-key' => $this->apiKey]);

        if ($response->failed()) {
            throw new RuntimeException('Arkesel SMS delivery failed: ' . $response->body());
        }
    }
}
