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
        'invitation',
    ];

    private const DEFAULT_LIFETIME = 5;

    // an invited person may not reach a computer for hours, so their code outlives the rest
    private const LIFETIMES = [
        'invitation' => 60,
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
            'expires_at' => now()->addMinutes($this->lifetimeFor($type)),
        ]);

        $this->sms->send($identifier, $this->messageFor($type, $plainCode));

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

    // says whether a code already sent is still usable, so a second one is not sent for nothing
    public function hasLiveCode(string $identifier, string $type): bool
    {
        $this->guardType($type);

        $otp = OtpCode::where('identifier', $identifier)
            ->where('type', $type)
            ->whereNull('used_at')
            ->latest()
            ->first();

        return $otp !== null && ! $otp->isExpired() && ! $otp->isExhausted();
    }

    public function markUsed(OtpCode $otp): void
    {
        $otp->update(['used_at' => now()]);
    }

    private function lifetimeFor(string $type): int
    {
        return self::LIFETIMES[$type] ?? self::DEFAULT_LIFETIME;
    }

    // an invited person has no idea what a code is for, so their message says where to go
    private function messageFor(string $type, string $plainCode): string
    {
        if ($type === 'invitation') {
            $link = rtrim(config('app.url'), '/') . '/activate';

            return "Welcome to NkwaLedger. Go to {$link}, enter your phone number, then this code: {$plainCode}. Valid for 1 hour.";
        }

        $minutes = $this->lifetimeFor($type);

        return "Your NkwaLedger code is: {$plainCode}. Valid for {$minutes} minutes.";
    }

    // stops a typo becoming a code nobody can ever verify
    private function guardType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown OTP type.');
        }
    }
}
