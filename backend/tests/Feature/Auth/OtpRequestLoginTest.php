<?php

use App\Models\OtpCode;
use App\Models\User;

test('a known phone gets a code', function () {
    $user = User::factory()->create(['phone' => '+233241234567']);

    $this->post('/login/otp', ['phone' => '+233241234567'])
        ->assertRedirect('/verify-otp');

    expect(OtpCode::where('identifier', '+233241234567')->exists())->toBeTrue();
});

test('an unknown phone gets the same reply', function () {
    $this->post('/login/otp', ['phone' => '+233249999999'])
        ->assertRedirect('/verify-otp');
});

test('an unknown phone never triggers an sms', function () {
    $this->post('/login/otp', ['phone' => '+233249999999']);

    expect(OtpCode::where('identifier', '+233249999999')->exists())->toBeFalse();
});

test('the reply never says whether the account exists', function () {
    $this->post('/login/otp', ['phone' => '+233249999999'])
        ->assertSessionHasNoErrors();
});
