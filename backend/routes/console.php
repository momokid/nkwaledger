<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// clears verification once the window has passed
Schedule::command('verification:expire')->dailyAt('02:00')->withoutOverlapping();

// builds up weather advisory history one day at a time
Schedule::command('weather:snapshot-advisories')->dailyAt('03:00')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
