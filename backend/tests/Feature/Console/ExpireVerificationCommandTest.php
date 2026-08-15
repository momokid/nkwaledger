<?php

use App\Models\User;

test('an overdue user is unverified', function () {
    $user = User::factory()->create(['next_verification_at' => now()->subDay()]);
    $user->assignRole('farmer');

    $this->artisan('verification:expire')->assertExitCode(0);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

test('a user still within the window is left alone', function () {
    $user = User::factory()->create(['next_verification_at' => now()->addDays(5)]);
    $user->assignRole('farmer');

    $this->artisan('verification:expire');

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('an admin is never expired', function () {
    $user = User::factory()->create(['next_verification_at' => now()->subDay()]);
    $user->assignRole('admin');

    $this->artisan('verification:expire');

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('a user with no deadline is left alone', function () {
    $user = User::factory()->create(['next_verification_at' => null]);
    $user->assignRole('farmer');

    $this->artisan('verification:expire');

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('an already unverified user is not touched twice', function () {
    $user = User::factory()->unverified()->create([
        'next_verification_at'      => now()->subDay(),
        'logins_since_verification' => 4,
    ]);
    $user->assignRole('farmer');

    $this->artisan('verification:expire');

    expect($user->fresh()->logins_since_verification)->toBe(4);
});

test('it reports how many were expired', function () {
    $first = User::factory()->create(['next_verification_at' => now()->subDay()]);
    $first->assignRole('farmer');

    $second = User::factory()->create(['next_verification_at' => now()->subDay()]);
    $second->assignRole('farmer');

    $this->artisan('verification:expire')
        ->expectsOutputToContain('2')
        ->assertExitCode(0);
});
