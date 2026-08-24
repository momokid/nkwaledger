<?php

use App\Contracts\SmsProvider;
use App\Models\OtpCode;
use App\Models\User;

// the login step deliberately sets a session for any number, so resend must check the account itself
test('resend sends nothing when no account holds that number', function () {
    $this->post('/login/otp', ['phone' => '0249999999']);

    $this->post('/resend-otp');

    expect(app(SmsProvider::class)->sentTo('0249999999'))->toBeFalse();
});

test('resend writes no code for a number with no account', function () {
    $this->post('/login/otp', ['phone' => '0249999999']);

    $this->post('/resend-otp');

    expect(OtpCode::where('identifier', '0249999999')->exists())->toBeFalse();
});

// the reply must look the same either way, or resend becomes a way to test numbers
test('resend replies the same whether or not the account exists', function () {
    User::factory()->create(['phone' => '0244004001']);

    $this->post('/login/otp', ['phone' => '0244004001']);
    $known = $this->post('/resend-otp');

    $this->post('/login/otp', ['phone' => '0249999998']);
    $unknown = $this->post('/resend-otp');

    expect($known->status())->toBe($unknown->status());
    $known->assertSessionHasNoErrors();
    $unknown->assertSessionHasNoErrors();
});

test('resend still works for a real account', function () {
    User::factory()->create(['phone' => '0244004002']);

    $this->post('/login/otp', ['phone' => '0244004002']);

    app(SmsProvider::class)->sent = [];

    $this->post('/resend-otp');

    expect(app(SmsProvider::class)->sentTo('0244004002'))->toBeTrue();
});

test('a registration resend works even though the account is unverified', function () {
    $this->post('/register', [
        'surname'               => 'Mensah',
        'first_name'            => 'Kwame',
        'phone'                 => '0244004003',
        'password'              => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    app(SmsProvider::class)->sent = [];

    $this->post('/resend-otp');

    expect(app(SmsProvider::class)->sentTo('0244004003'))->toBeTrue();
});

test('an invitation resend works for an account with no password', function () {
    $staff = User::factory()->unverified()->create([
        'phone'    => '0244004004',
        'password' => null,
    ]);
    $staff->assignRole('agent');

    $this->post('/activate', ['phone' => '0244004004']);

    app(SmsProvider::class)->sent = [];

    $this->post('/resend-otp');

    expect(app(SmsProvider::class)->sentTo('0244004004'))->toBeTrue();
});

test('resend does nothing without a pending session', function () {
    $this->post('/resend-otp');

    expect(app(SmsProvider::class)->sent)->toBeEmpty();
});
