<?php

use App\Contracts\SmsProvider;
use App\Models\User;

function registerPayload(string $phone): array
{
    return [
        'surname'               => 'Mensah',
        'first_name'            => 'Kwame',
        'phone'                 => $phone,
        'password'              => 'Password@123',
        'password_confirmation' => 'Password@123',
    ];
}

// every registration costs an sms, so one machine must not be able to run up the bill
test('one ip is capped at five registrations an hour', function () {
    for ($i = 1; $i <= 5; $i++) {
        $this->post('/register', registerPayload('02440050' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)));
        $this->post('/logout');
    }

    $response = $this->post('/register', registerPayload('0244005099'));

    $response->assertStatus(429);
});

test('a blocked registration creates no account', function () {
    for ($i = 1; $i <= 5; $i++) {
        $this->post('/register', registerPayload('02440051' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)));
        $this->post('/logout');
    }

    $this->post('/register', registerPayload('0244005198'));

    expect(User::where('phone', '0244005198')->exists())->toBeFalse();
});

test('a blocked registration sends no sms', function () {
    for ($i = 1; $i <= 5; $i++) {
        $this->post('/register', registerPayload('02440052' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)));
        $this->post('/logout');
    }

    app(SmsProvider::class)->sent = [];

    $this->post('/register', registerPayload('0244005297'));

    expect(app(SmsProvider::class)->sent)->toBeEmpty();
});

test('a first registration is not blocked', function () {
    $this->post('/register', registerPayload('0244005301'))
        ->assertRedirect('/verify-otp');

    expect(User::where('phone', '0244005301')->exists())->toBeTrue();
});
