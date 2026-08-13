<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a logged in user can request a verification code', function () {
    $user = User::factory()->create(['phone' => '+233241234567']);

    $this->actingAs($user)->post('/verify-phone/send')->assertRedirect();

    expect(OtpCode::where('identifier', '+233241234567')
        ->where('type', 'phone_verification')
        ->exists())->toBeTrue();
});

test('a guest cannot request a verification code', function () {
    $this->post('/verify-phone/send')->assertRedirect('/login');
});

test('the code always goes to the account phone', function () {
    $user = User::factory()->create(['phone' => '+233241234567']);

    $this->actingAs($user)->post('/verify-phone/send', ['phone' => '+233249999999']);

    expect(OtpCode::where('identifier', '+233249999999')->exists())->toBeFalse();
});

test('a correct code verifies the phone', function () {
    $user = User::factory()->create(['phone' => '+233241234567']);

    OtpCode::create([
        'identifier' => '+233241234567',
        'code'       => Hash::make('123456'),
        'type'       => 'phone_verification',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($user)->post('/verify-phone/confirm', ['code' => '123456']);

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('a correct code also sets the login threshold', function () {
    $user = User::factory()->create(['phone' => '+233241234567']);
    $user->assignRole('farmer');

    OtpCode::create([
        'identifier' => '+233241234567',
        'code'       => Hash::make('123456'),
        'type'       => 'phone_verification',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($user)->post('/verify-phone/confirm', ['code' => '123456']);

    expect($user->fresh()->verification_login_threshold)->toBeGreaterThanOrEqual(15);
});

test('a wrong code leaves the user unverified', function () {
    $user = User::factory()->create(['phone' => '+233241234567']);

    OtpCode::create([
        'identifier' => '+233241234567',
        'code'       => Hash::make('123456'),
        'type'       => 'phone_verification',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($user)->post('/verify-phone/confirm', ['code' => '999999'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

test('a login code cannot be used to verify a phone', function () {
    $user = User::factory()->create(['phone' => '+233241234567']);

    OtpCode::create([
        'identifier' => '+233241234567',
        'code'       => Hash::make('123456'),
        'type'       => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($user)->post('/verify-phone/confirm', ['code' => '123456'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->phone_verified_at)->toBeNull();
});
