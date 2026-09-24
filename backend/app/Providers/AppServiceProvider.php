<?php

namespace App\Providers;

use App\Contracts\SmsProvider;
use App\Models\User;
use App\Services\Sms\ArkeselSmsProvider;
use App\Services\Sms\LogSmsProvider;
use App\Session\RoleAwareDatabaseSessionHandler;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Observers\AuditableObserver;
use RuntimeException;

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
        // an explicit flag rather than app()->isLocal(), so arkesel can still be
        // tested from a local machine on demand
        $this->app->bind(SmsProvider::class, fn() => env('SMS_DRIVER', 'arkesel') === 'log'
            ? new LogSmsProvider()
            : new ArkeselSmsProvider(
                apiKey: config('services.arkesel.key'),
                sender: config('services.arkesel.sender'),
            ));
    }

    public function boot(): void
    {
        // a test bypass code left on in production would let anyone log in as
        // whatever number is listed - this must fail at boot, not at OTP time
        if (app()->environment('production') && trim((string) config('otp.test_phones')) !== '') {
            throw new RuntimeException('OTP_TEST_PHONES must not be set in production.');
        }

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

        // the number comes from the session, so a caller cannot spread the count across many keys.
        // an email-channel login is keyed by the phone on file instead of the email itself, so
        // falling back to sms mid-attempt shares the same budget rather than resetting it
        RateLimiter::for('otp-resend', function (Request $request) {
            $identifier = $request->session()->get('auth.login_identifier');
            $key        = $identifier;

            if ($identifier && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $key = optional(User::where('email', $identifier)->first())->phone ?? $identifier;
            }

            return [
                Limit::perHour(config('otp.throttle.resend.per_phone'))
                    ->by('resend-phone:' . $key),
                Limit::perHour(config('otp.throttle.resend.per_ip'))
                    ->by('resend-ip:' . $request->ip()),
            ];
        });

        Route::model('farmer', \App\Models\FarmerProfile::class);
        // anything that is not a uuid is not an address, so it never reaches the database
        Route::pattern('farmer', '[0-9a-fA-F-]{36}');

        // swaps in a lifetime that varies per session's user (see the handler for why)
        $this->app->make('session')->extend('database', fn($app) => new RoleAwareDatabaseSessionHandler(
            $app['db']->connection($app['config']['session.connection']),
            $app['config']['session.table'],
            $app['config']['session.lifetime'],
            $app,
        ));

        // registered here rather than in the model, since observing during boot re-enters the cycle
        foreach (self::AUDITED_MODELS as $model) {
            $model::observe(AuditableObserver::class);
        }
    }
}
