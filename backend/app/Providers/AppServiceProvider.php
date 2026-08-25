<?php

namespace App\Providers;

use App\Contracts\SmsProvider;
use App\Services\Sms\ArkeselSmsProvider;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Observers\AuditableObserver;

class AppServiceProvider extends ServiceProvider
{
    // every model whose history has to survive an audit
    private const AUDITED_MODELS = [
        \App\Models\User::class,
        \App\Models\UserPermissionDenial::class,
        \App\Models\FarmType::class,
        \App\Models\FarmTypeCategory::class,
        \App\Models\FarmerGroup::class,
        \App\Models\FarmerGroupType::class,
        \App\Models\Region::class,
        \App\Models\District::class,
        \App\Models\Community::class,
        \App\Models\LedgerClass::class,
        \App\Models\LedgerCategory::class,
        \App\Models\LedgerSubcategory::class,
        \App\Models\LedgerType::class,
        \App\Models\LedgerControl::class,
        \App\Models\LedgerAccount::class,
    ];

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

        // each registration costs an sms, so one machine cannot run up the bill
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        // a per-account limit never fires against stuffing, which tries many accounts once each
        RateLimiter::for('login', function (Request $request) {
            return Limit::perHour(20)->by($request->ip());
        });

        // the number comes from the session, so a caller cannot spread the count across many keys
        RateLimiter::for('otp-resend', fn(Request $request) => [
            Limit::perHour(config('otp.throttle.resend.per_phone'))
                ->by('resend-phone:' . $request->session()->get('auth.login_identifier')),
            Limit::perHour(config('otp.throttle.resend.per_ip'))
                ->by('resend-ip:' . $request->ip()),
        ]);

        Route::model('farmer', \App\Models\FarmerProfile::class);
        
        // registered here rather than in the model, since observing during boot re-enters the cycle
        foreach (self::AUDITED_MODELS as $model) {
            $model::observe(AuditableObserver::class);
        }
    }
}
