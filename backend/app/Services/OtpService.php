<?php

namespace App\Services;

use App\Contracts\SmsProvider;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function __construct(private readonly SmsProvider $sms) {}

    public function generate(string $identifier, string $type): OtpCode
    {
        $plainCode = (string) random_int(100000, 999999);

        $otp = OtpCode::create([
            'identifier' => $identifier,
            'code'       => Hash::make($plainCode),
            'type'       => $type,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->sms->send($identifier, "Your NkwaLedger code is: {$plainCode}. Valid for 5 minutes.");

        return $otp;
    }

    public function verify(string $identifier, string $code, string $type): bool
    {
        $otp = OtpCode::where('identifier', $identifier)
            ->where('type', $type)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $otp || $otp->isExpired() || $otp->isExhausted()) {
            return false;
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code)) {
            return false;
        }

        $this->markUsed($otp);

        return true;
    }

    public function markUsed(OtpCode $otp): void
    {
        $otp->update(['used_at' => now()]);
    }
}
