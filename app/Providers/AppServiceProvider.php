<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Services\Sms\SmsSender;
use App\Services\Sms\FakeSmsSender;
use App\Services\Sms\TermiiSmsSender;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('production')) {
            $this->app->bind(SmsSender::class, TermiiSmsSender::class);
        } else {
            $this->app->bind(SmsSender::class, FakeSmsSender::class);
        }
    }

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
