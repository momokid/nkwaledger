<?php

namespace App\Providers;

use App\Contracts\SmsProvider;
use App\Services\Sms\ArkeselSmsProvider;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsProvider::class, fn () => new ArkeselSmsProvider(
            apiKey: config('services.arkesel.key'),
            sender: config('services.arkesel.sender'),
        ));
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
