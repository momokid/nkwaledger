<?php

namespace App\Services;

use App\Contracts\SmsProvider;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class OtpService
{
    // the only reasons we ever send a code
    public const TYPES = [
        'registration',
        'login',
        'password_reset',
        'phone_verification',
    ];

    public function __construct(private readonly SmsProvider $sms) {}

    public function generate(string $identifier, string $type): OtpCode
    {
        $this->guardType($type);

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
        $this->guardType($type);

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

    // stops a typo becoming a code nobody can ever verify
    private function guardType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown OTP type.');
        }
    }
}
