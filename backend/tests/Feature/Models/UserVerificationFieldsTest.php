<?php

use App\Models\User;

test('a new user starts with no logins since verification', function () {
    $user = User::factory()->create();

    expect($user->fresh()->logins_since_verification)->toBe(0);
});

test('a new user has no verification deadline set', function () {
    $user = User::factory()->create();

    expect($user->fresh()->next_verification_at)->toBeNull();
});

test('a new user is not phone verified', function () {
    $user = User::factory()->unverified()->create();

    expect($user->fresh()->phone_verified_at)->toBeNull();
});
