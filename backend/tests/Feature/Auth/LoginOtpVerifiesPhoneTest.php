<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function seedLoginOtp(string $phone, string $code = '123456'): void
{
    OtpCode::create([
        'identifier' => $phone,
        'code'       => Hash::make($code),
        'type'       => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);
}

test('a successful login otp verifies the phone', function () {
    $user = User::factory()->create(['phone' => '+233241234567']);
    $user->assignRole('farmer');

    seedLoginOtp('+233241234567');

    $this->post('/verify-otp', [
        'identifier' => '+233241234567',
        'code'       => '123456',
        'type'       => 'login',
    ]);

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('it also sets the login threshold', function () {
    $user = User::factory()->create(['phone' => '+233241234567']);
    $user->assignRole('farmer');

    seedLoginOtp('+233241234567');

    $this->post('/verify-otp', [
        'identifier' => '+233241234567',
        'code'       => '123456',
        'type'       => 'login',
    ]);

    expect($user->fresh()->verification_login_threshold)->toBeGreaterThanOrEqual(15);
});

test('a failed login otp leaves the phone unverified', function () {
    $user = User::factory()->unverified()->create(['phone' => '+233241234567']);
    $user->assignRole('farmer');

    seedLoginOtp('+233241234567');

    $this->post('/verify-otp', [
        'identifier' => '+233241234567',
        'code'       => '999999',
        'type'       => 'login',
    ]);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

test('a registration otp does not verify the phone', function () {
    $user = User::factory()->unverified()->create(['phone' => '+233241234567']);
    $user->assignRole('farmer');

    OtpCode::create([
        'identifier' => '+233241234567',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->post('/verify-otp', [
        'identifier' => '+233241234567',
        'code'       => '123456',
        'type'       => 'registration',
    ]);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});
