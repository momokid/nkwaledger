<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OtpService
{
    /**
     * Generate and store a new OTP for a phone number.
     *
     * @param string $phoneNumber
     * @param string $channel
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return string  The raw OTP (to be sent via SMS later)
     */
    public function generate(
        string $phoneNumber,
        string $channel = 'web',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): string {
        // 1. Invalidate existing unused OTPs for this phone
        DB::table('one_time_passwords')
            ->where('phone_number', $phoneNumber)
            ->whereNull('used_at')
            ->update([
                'expires_at' => Carbon::now(),
            ]);

        // 2. Generate secure 6-digit numeric OTP
        $otp = (string) random_int(100000, 999999);

        // 3. Store hashed OTP
        DB::table('one_time_passwords')->insert([
            'phone_number' => $phoneNumber,
            'otp_hash'     => Hash::make($otp),
            'expires_at'   => Carbon::now()->addMinutes(5),
            'attempts'     => 0,
            'channel'      => $channel,
            'ip_address'   => $ipAddress,
            'user_agent'   => $userAgent,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // 4. Return raw OTP (ONLY for SMS sending layer)
        return $otp;
    }
}
