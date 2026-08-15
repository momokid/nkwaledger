<?php

use App\Models\User;
use App\Services\PhoneVerificationService;

beforeEach(function () {
    $this->service = app(PhoneVerificationService::class);
});

test('it marks the phone as verified', function () {
    $user = User::factory()->create();

    $this->service->markVerified($user);

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('it resets the login counter', function () {
    $user = User::factory()->create(['logins_since_verification' => 12]);

    $this->service->markVerified($user);

    expect($user->fresh()->logins_since_verification)->toBe(0);
});

test('it rolls a threshold between 15 and 30', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $this->service->markVerified($user);

    expect($user->fresh()->verification_login_threshold)
        ->toBeGreaterThanOrEqual(15)
        ->toBeLessThanOrEqual(30);
});

test('admins get no threshold because they verify every login', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->service->markVerified($user);

    expect($user->fresh()->verification_login_threshold)->toBeNull();
});

test('it sets the next deadline 30 days ahead', function () {
    $user = User::factory()->create();

    $this->service->markVerified($user);

    expect($user->fresh()->next_verification_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

test('verifying again rolls a fresh threshold', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $this->service->markVerified($user);
    $first = $user->fresh()->verification_login_threshold;

    $user->update(['verification_login_threshold' => 99]);
    $this->service->markVerified($user);

    expect($user->fresh()->verification_login_threshold)->not->toBe(99);
})->repeat(5);
