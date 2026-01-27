<?php

namespace Tests\Feature;

use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_otp_can_be_verified(): void
    {
        $service = new OtpService();

        $otp = $service->generate('233501234567');

        $result = $service->verify('233501234567', $otp);

        $this->assertTrue($result);

        $this->assertDatabaseHas('one_time_passwords', [
            'phone_number' => '233501234567',
        ]);
    }

    public function test_invalid_otp_is_rejected(): void
    {
        $service = new OtpService();

        $service->generate('233501234567');

        $this->assertFalse(
            $service->verify('233501234567', '000000')
        );
    }

    public function test_expired_otp_is_rejected(): void
    {
        DB::table('one_time_passwords')->insert([
            'phone_number' => '233501234567',
            'otp_hash' => bcrypt('123456'),
            'expires_at' => now()->subMinute(),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new OtpService();

        $this->assertFalse(
            $service->verify('233501234567', '123456')
        );
    }

    public function test_otp_cannot_be_used_twice(): void
    {
        $service = new OtpService();

        $otp = $service->generate('233501234567');

        $this->assertTrue(
            $service->verify('233501234567', $otp)
        );

        // Second attempt should fail
        $this->assertFalse(
            $service->verify('233501234567', $otp)
        );
    }

    public function test_otp_is_locked_after_max_attempts(): void
    {
        $service = new OtpService();

        $service->generate('233501234567');

        for ($i = 0; $i < 5; $i++) {
            $service->verify('233501234567', '111111');
        }

        $this->assertFalse(
            $service->verify('233501234567', '111111')
        );
    }
}
