<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\Sms\SmsSender;
use App\Services\Auth\PhoneNumberNormalizer;

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

    public function __construct(private SmsSender $smsSender, private PhoneNumberNormalizer $phoneNormalizer) {}

    public function generate(
        string $phoneNumber,
        string $channel = 'web',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): string {

        $lastOtp = DB::table('one_time_passwords')
            ->where('phone_number', $phoneNumber)
            ->whereNull('used_at')
            ->orderByDesc('created_at')
            ->first();


        if ($lastOtp && Carbon::parse($lastOtp->created_at)->diffInSeconds(now()) < 60) {
            throw new \DomainException(
                'Please wait before requesting another code.'
            );
        }

        // Invalidate existing unused OTPs for this phone
        DB::table('one_time_passwords')
            ->where('phone_number', $phoneNumber)
            ->whereNull('used_at')
            ->update([
                'expires_at' => Carbon::now(),
            ]);

        // Generate secure 6-digit numeric OTP
        $otp = (string) random_int(100000, 999999);

        // Store hashed OTP
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

        // Send OTP via SMS
        $message = "Your NkwaLedger login code is {$otp}. Expires in 5 minutes.";

        $this->smsSender->send(
            $phoneNumber,
            $message
        );

        // Return raw OTP (ONLY for SMS sending layer)
        return $otp;
    }

    /**
     * Verify an OTP for a phone number.
     *
     * @return bool True if OTP is valid, false otherwise
     */

    public function verify(string $phoneNumber, string $otp): bool
    {
        $phoneNumber = $this->phoneNormalizer->normalize($phoneNumber);
        $record = DB::table('one_time_passwords')
            ->where('phone_number', $phoneNumber)
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$record) return false;

        //Expired OTP
        if (!Hash::check($otp, $record->otp_hash)) {
            //Increment attempts
            DB::table('one_time_passwords')
                ->where('id', $record->id)
                ->increment('attempts');
            return false;
        }

        //OTP is valid, mark as used
        DB::table('one_time_passwords')
            ->where('id', $record->id)
            ->update(['used_at' => Carbon::now(), "updated_at" => Carbon::now()]);

        return true;
    }
}
