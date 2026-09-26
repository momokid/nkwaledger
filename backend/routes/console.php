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

// a silent supplier's report moves to admin, with admin's own fresh deadline
Schedule::command('marketplace:escalate-silent-reports')->dailyAt('05:30')->withoutOverlapping();

// admin's own window ending unresolved only ever alerts - never auto-suspends
Schedule::command('marketplace:alert-overdue-reports')->dailyAt('05:45')->withoutOverlapping();

// an order that never got both taps within the confirmation window closes for good
Schedule::command('marketplace:close-unconfirmed-orders')->dailyAt('06:00')->withoutOverlapping();

// a crop listing gets one reminder before its own set expiry date, then expires
Schedule::command('marketplace:expire-crop-listings')->dailyAt('06:15')->withoutOverlapping();

// a non-expiring listing gets one still-available check, then hides on silence
Schedule::command('marketplace:prompt-still-available-listings')->dailyAt('06:30')->withoutOverlapping();

// stock can shrink after a listing already claimed some of it - this keeps a
// listing from ever offering more than its batch actually still has
Schedule::command('marketplace:reconcile-listing-stock')->dailyAt('06:45')->withoutOverlapping();

// a produce sale that never got both taps within the confirmation window closes for good
Schedule::command('marketplace:close-unconfirmed-produce-sales')->dailyAt('07:00')->withoutOverlapping();

// an unanswered contact request never reveals a number past its own reply window
Schedule::command('marketplace:expire-contact-requests')->dailyAt('07:15')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
