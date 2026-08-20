<?php

use App\Contracts\SmsProvider;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the otp page cannot be opened without a pending code', function () {
    $response = $this->get('/verify-otp');

    $response->assertRedirect('/login');
});

test('the otp page opens when a code is pending', function () {
    $response = $this->withSession([
        'auth.login_identifier' => '0244000001',
        'auth.otp_type'         => 'login',
    ])->get('/verify-otp');

    $response->assertOk();
});

test('a code cannot be submitted without a pending session', function () {
    $response = $this->post('/verify-otp', ['code' => '123456']);

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('the phone in the session is used, not the one posted', function () {
    $mine = User::factory()->create(['phone' => '0244000001']);
    $mine->assignRole('farmer');

    $theirs = User::factory()->create(['phone' => '0244000002']);
    $theirs->assignRole('farmer');

    OtpCode::create([
        'identifier' => '0244000002',
        'code'       => Hash::make('654321'),
        'type'       => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);

    // session says one number, the request body claims another and offers that other number's code
    $response = $this->withSession([
        'auth.login_identifier' => '0244000001',
        'auth.otp_type'         => 'login',
    ])->post('/verify-otp', [
        'identifier' => '0244000002',
        'code'       => '654321',
    ]);

    $response->assertSessionHasErrors(['code']);
    $this->assertGuest();
});

test('a valid code for the session phone logs the user in', function () {
    $user = User::factory()->create(['phone' => '0244000001']);
    $user->assignRole('farmer');

    OtpCode::create([
        'identifier' => '0244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);

    $response = $this->withSession([
        'auth.login_identifier' => '0244000001',
        'auth.otp_type'         => 'login',
    ])->post('/verify-otp', ['code' => '123456']);

    $response->assertRedirect('/farmer/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('resend sends to the session phone, not the one posted', function () {
    $this->withSession([
        'auth.login_identifier' => '0244000001',
        'auth.otp_type'         => 'login',
    ])->post('/resend-otp', ['identifier' => '0244000002']);

    expect(app(SmsProvider::class)->sentTo('0244000001'))->toBeTrue();
    expect(app(SmsProvider::class)->sentTo('0244000002'))->toBeFalse();
});

test('resend does nothing without a pending session', function () {
    $response = $this->post('/resend-otp', ['identifier' => '0244000002']);

    $response->assertRedirect('/login');
    expect(app(SmsProvider::class)->sentTo('0244000002'))->toBeFalse();
});
