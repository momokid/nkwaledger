<?php

namespace App\Providers;

use App\Contracts\SmsProvider;
use App\Services\Sms\ArkeselSmsProvider;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsProvider::class, fn() => new ArkeselSmsProvider(
            apiKey: config('services.arkesel.key'),
            sender: config('services.arkesel.sender'),
        ));
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // caps both the number being targeted and the machine doing the asking
        RateLimiter::for('otp-request', fn(Request $request) => [
            Limit::perHour(config('otp.throttle.login.per_phone'))
                ->by('otp-phone:' . $request->input('phone')),
            Limit::perHour(config('otp.throttle.login.per_ip'))
                ->by('otp-ip:' . $request->ip()),
        ]);

        // the number comes from the session, so a caller cannot spread the count across many keys
        RateLimiter::for('otp-resend', fn(Request $request) => [
            Limit::perHour(config('otp.throttle.resend.per_phone'))
                ->by('resend-phone:' . $request->session()->get('auth.login_identifier')),
            Limit::perHour(config('otp.throttle.resend.per_ip'))
                ->by('resend-ip:' . $request->ip()),
        ]);
    }
}
