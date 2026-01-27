<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_phone_and_otp(): void
    {
        $user = User::factory()->create([
            'phone_number' => '233501234567',
        ]);

        // Request OTP
        $response = $this->postJson('/auth/otp/request', [
            'phone_number' => '233501234567',
        ]);

        $response->assertOk();
        $otp = $response->json('otp');

        // Verify OTP
        $verify = $this->postJson('/auth/otp/verify', [
            'phone_number' => '233501234567',
            'otp' => $otp,
        ]);

        $verify->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
