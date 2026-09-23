<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// clears verification once the window has passed
Schedule::command('verification:expire')->dailyAt('02:00')->withoutOverlapping();

// builds up weather advisory history one day at a time
Schedule::command('weather:snapshot-advisories')->dailyAt('03:00')->withoutOverlapping();

// nudges farmers about credit still outstanding, repeating every three days until settled
Schedule::command('credit:remind')->dailyAt('04:00')->withoutOverlapping();

// once per stale period, not daily - the flag it sets is what stops the daily repeat
Schedule::command('marketplace:alert-stale-prices')->dailyAt('05:00')->withoutOverlapping();

// once per product, the alert-days setting before its expiry date
Schedule::command('marketplace:alert-expiring-products')->dailyAt('05:15')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
