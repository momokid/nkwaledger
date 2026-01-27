<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OtpRateLimitingTest extends TestCase
{
    public function test_phone_number_is_rate_limited_for_otp_requests(): void
    {
        $request = Request::create('/', 'POST', [
            'phone_number' => '233501234567'
        ]);

        $limiter = app(RateLimiter::class);

        for ($i = 0; $i < 3; $i++) {
            $this->assertFalse(
                $limiter->tooManyAttempts(
                    'otp-request-phone:' . $request->input('phone_number'),
                    3
                )
            );
            $limiter->hit(
                'otp-request-phone:' . $request->input('phone_number'),
                600
            );
        }

        $this->assertTrue(
            $limiter->tooManyAttempts(
                'otp-request-phone:' . $request->input('phone_number'),
                3
            )
        );
    }
}
