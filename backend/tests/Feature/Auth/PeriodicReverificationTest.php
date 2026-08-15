<?php

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

function fireLogin(User $user): void
{
    event(new Login('web', $user, false));
}

test('a login increases the counter', function () {
    $user = User::factory()->create([
        'logins_since_verification'    => 3,
        'verification_login_threshold' => 20,
    ]);
    $user->assignRole('farmer');

    fireLogin($user);

    expect($user->fresh()->logins_since_verification)->toBe(4);
});

test('reaching the threshold unverifies the phone', function () {
    $user = User::factory()->create([
        'logins_since_verification'    => 19,
        'verification_login_threshold' => 20,
    ]);
    $user->assignRole('farmer');

    fireLogin($user);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

test('staying below the threshold keeps the phone verified', function () {
    $user = User::factory()->create([
        'logins_since_verification'    => 5,
        'verification_login_threshold' => 20,
    ]);
    $user->assignRole('farmer');

    fireLogin($user);

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('the deadline is kept so we can tell overdue from never verified', function () {
    $user = User::factory()->create([
        'logins_since_verification'    => 19,
        'verification_login_threshold' => 20,
        'next_verification_at'         => now()->addDays(10),
    ]);
    $user->assignRole('farmer');

    fireLogin($user);

    expect($user->fresh()->next_verification_at)->not->toBeNull();
});

test('an admin counter is never touched', function () {
    $user = User::factory()->create(['logins_since_verification' => 0]);
    $user->assignRole('admin');

    fireLogin($user);

    expect($user->fresh()->logins_since_verification)->toBe(0);
});

test('an already unverified user is left alone', function () {
    $user = User::factory()->unverified()->create([
        'logins_since_verification'    => 7,
        'verification_login_threshold' => 20,
    ]);
    $user->assignRole('farmer');

    fireLogin($user);

    expect($user->fresh()->logins_since_verification)->toBe(7);
});

test('a user with no threshold is left alone', function () {
    $user = User::factory()->create([
        'logins_since_verification'    => 2,
        'verification_login_threshold' => null,
    ]);
    $user->assignRole('farmer');

    fireLogin($user);

    expect($user->fresh()->logins_since_verification)->toBe(2);
});
