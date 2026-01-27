<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp-request-phone', function (Request $request) {
            return Limit::perMinute(10, 3)->by($request->input('phone-number'));
        });

        RateLimiter::for('otp-verify-ip', function (Request $request) {
            return Limit::perMinutes(10, 10)->by($request->ip());
        });

        RateLimiter::for('otp-verify-phone', function (request $request) {
            return Limit::perMinutes(10, 10)->by($request->input('phone-number'));
        });
    }
}
