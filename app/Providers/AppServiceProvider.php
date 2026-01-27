<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('otp-request-phone', function (Request $request) {
            return Limit::perMinutes(10, 3)->by(
                $request->input('phone_number')
            );
        });

        RateLimiter::for('otp-request-ip', function (Request $request) {
            return Limit::perMinutes(10, 10)->by(
                $request->ip()
            );
        });

        RateLimiter::for('otp-verify-phone', function (Request $request) {
            return Limit::perMinutes(10, 10)->by(
                $request->input('phone_number')
            );
        });
    }
}
