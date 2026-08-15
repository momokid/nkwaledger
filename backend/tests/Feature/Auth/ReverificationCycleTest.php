<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Hash;

test('a full cycle resets the counter and rolls a new threshold', function () {
    $user = User::factory()->create([
        'phone'                        => '+233241234567',
        'logins_since_verification'    => 19,
        'verification_login_threshold' => 20,
    ]);
    $user->assignRole('farmer');

    // the login that trips the threshold
    event(new Login('web', $user, false));

    expect($user->fresh()->phone_verified_at)->toBeNull();

    // the gate now blocks a real route
    $this->actingAs($user->fresh())
        ->get('/farmer/dashboard')
        ->assertOk();

    OtpCode::create([
        'identifier' => '+233241234567',
        'code'       => Hash::make('123456'),
        'type'       => 'phone_verification',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($user->fresh())
        ->post('/verify-phone/confirm', ['code' => '123456']);

    $after = $user->fresh();

    expect($after->phone_verified_at)->not->toBeNull();
    expect($after->logins_since_verification)->toBe(0);
    expect($after->verification_login_threshold)->toBeGreaterThanOrEqual(15);
    expect($after->verification_login_threshold)->toBeLessThanOrEqual(30);
});

test('the new threshold applies to the next cycle', function () {
    $user = User::factory()->create([
        'logins_since_verification'    => 0,
        'verification_login_threshold' => 15,
    ]);
    $user->assignRole('farmer');

    // fourteen logins should not trip it
    for ($i = 0; $i < 14; $i++) {
        event(new Login('web', $user->fresh(), false));
    }

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
    expect($user->fresh()->logins_since_verification)->toBe(14);

    // the fifteenth does
    event(new Login('web', $user->fresh(), false));

    expect($user->fresh()->phone_verified_at)->toBeNull();
});
