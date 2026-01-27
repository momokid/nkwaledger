<?php

namespace Tests\Feature;

use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_and_stores_an_otp(): void
    {
        $service = new OtpService();

        $otp = $service->generate(
            phoneNumber: '233501234567',
            channel: 'web'
        );

        // OTP format
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);

        // OTP stored
        $this->assertDatabaseHas('one_time_passwords', [
            'phone_number' => '233501234567',
            'channel' => 'web',
        ]);
    }

    public function test_old_otps_are_invalidated_when_new_one_is_generated(): void
    {
        $service = new OtpService();

        // First OTP
        $service->generate('233501234567');

        // Second OTP
        $service->generate('233501234567');

        $activeOtps = DB::table('one_time_passwords')
            ->where('phone_number', '233501234567')
            ->where('expires_at', '>', now())
            ->count();

        // Only one active OTP should exist
        $this->assertEquals(1, $activeOtps);
    }
}
