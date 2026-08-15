<?php

namespace App\Services;

use App\Contracts\SmsProvider;
use App\Mail\LoginFromNewDeviceMail;
use App\Models\User;
use App\Models\UserKnownDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LoginAnomalyService
{
    // farmers are left out on purpose: shared phones and cafe wifi would trip this on almost every login
    public const TRACKED_ROLES = ['admin', 'agent', 'vet', 'adviser', 'supplier'];

    public function __construct(private readonly SmsProvider $sms) {}

    // answers whether this login needs an otp step, without touching the database
    public function requiresOtp(User $user, Request $request): bool
    {
        if (! $this->isTracked($user)) {
            return false;
        }

        return ! $this->isKnownDevice($user, $request);
    }

    // records the device and alerts the user if it has not been seen before
    public function checkAndRecord(User $user, Request $request): void
    {
        if (! $this->isTracked($user)) {
            return;
        }

        $fingerprint = $this->fingerprint($request);

        $known = UserKnownDevice::where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($known) {
            $known->update(['last_seen_at' => now()]);

            return;
        }

        UserKnownDevice::create([
            'user_id'      => $user->id,
            'fingerprint'  => $fingerprint,
            'last_seen_at' => now(),
        ]);

        $this->alert($user, $request);
    }

    protected function isTracked(User $user): bool
    {
        return $user->hasAnyRole(self::TRACKED_ROLES);
    }

    protected function isKnownDevice(User $user, Request $request): bool
    {
        return UserKnownDevice::where('user_id', $user->id)
            ->where('fingerprint', $this->fingerprint($request))
            ->exists();
    }

    protected function fingerprint(Request $request): string
    {
        return hash('sha256', $request->ip() . '|' . $request->userAgent());
    }

    protected function alert(User $user, Request $request): void
    {
        $ip  = $request->ip();
        $now = now();

        $this->sms->send(
            $user->phone,
            "New login detected on your NkwaLedger account from IP {$ip} at {$now->format('d M Y, H:i')}. If this wasn't you, contact support immediately."
        );

        if ($user->email) {
            Mail::to($user->email)->send(new LoginFromNewDeviceMail($user, $ip, $now));
        }
    }
}
