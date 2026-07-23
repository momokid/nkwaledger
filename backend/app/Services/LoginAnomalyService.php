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
    public function __construct(private readonly SmsProvider $sms) {}

    // checks whether this login is from a device not seen before for this user, alerting if so
    public function checkAndRecord(User $user, Request $request): void
    {
        if (! $user->hasRole('admin') && ! $user->hasRole('agent')) {
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
            'user_id' => $user->id,
            'fingerprint' => $fingerprint,
            'last_seen_at' => now(),
        ]);

        $this->alert($user, $request);
    }

    protected function fingerprint(Request $request): string
    {
        return hash('sha256', $request->ip() . '|' . $request->userAgent());
    }

    protected function alert(User $user, Request $request): void
    {
        $ip = $request->ip();
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
