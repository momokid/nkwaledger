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

// stands in for the login step that would normally put these there
function pendingOtp(string $phone, string $type = 'login'): array
{
    return [
        'auth.login_identifier' => $phone,
        'auth.otp_type'         => $type,
    ];
}

test('a successful login otp verifies the phone', function () {
    $user = User::factory()->unverified()->create(['phone' => '+233241234567']);
    $user->assignRole('farmer');

    seedLoginOtp('+233241234567');

    $this->withSession(pendingOtp('+233241234567'))
        ->post('/verify-otp', ['code' => '123456']);

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('it also sets the login threshold', function () {
    $user = User::factory()->unverified()->create(['phone' => '+233241234567']);
    $user->assignRole('farmer');

    seedLoginOtp('+233241234567');

    $this->withSession(pendingOtp('+233241234567'))
        ->post('/verify-otp', ['code' => '123456']);

    expect($user->fresh()->verification_login_threshold)->toBeGreaterThanOrEqual(15);
});

test('a failed login otp leaves the phone unverified', function () {
    $user = User::factory()->unverified()->create(['phone' => '+233241234567']);
    $user->assignRole('farmer');

    seedLoginOtp('+233241234567');

    $this->withSession(pendingOtp('+233241234567'))
        ->post('/verify-otp', ['code' => '999999']);

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

    $this->withSession(pendingOtp('+233241234567', 'registration'))
        ->post('/verify-otp', ['code' => '123456']);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});
