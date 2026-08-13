<?php

use App\Models\User;
use Illuminate\Support\Carbon;

test('login count is an integer', function () {
    $user = User::factory()->create(['logins_since_verification' => 5]);

    expect($user->fresh()->logins_since_verification)->toBeInt();
});

test('login threshold is an integer', function () {
    $user = User::factory()->create(['verification_login_threshold' => 20]);

    expect($user->fresh()->verification_login_threshold)->toBeInt();
});

test('next verification date is a date object', function () {
    $user = User::factory()->create(['next_verification_at' => now()]);

    expect($user->fresh()->next_verification_at)->toBeInstanceOf(Carbon::class);
});
