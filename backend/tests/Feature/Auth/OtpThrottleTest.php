<?php

use App\Contracts\SmsProvider;
use App\Models\User;

function smsCountTo(string $phone): int
{
    return collect(app(SmsProvider::class)->sent)->where('phone', $phone)->count();
}

function pendingFor(string $phone): array
{
    return [
        'auth.login_identifier' => $phone,
        'auth.otp_type'         => 'login',
    ];
}

test('a fourth otp login request for the same phone within an hour is blocked', function () {
    User::factory()->create(['phone' => '0244000001']);

    for ($i = 0; $i < 3; $i++) {
        $this->post('/login/otp', ['phone' => '0244000001']);
    }

    $this->post('/login/otp', ['phone' => '0244000001'])->assertStatus(429);
});

test('a blocked otp login request sends no sms', function () {
    User::factory()->create(['phone' => '0244000001']);

    for ($i = 0; $i < 4; $i++) {
        $this->post('/login/otp', ['phone' => '0244000001']);
    }

    expect(smsCountTo('0244000001'))->toBe(3);
});

test('a different phone from the same ip is still allowed', function () {
    User::factory()->create(['phone' => '0244000001']);
    User::factory()->create(['phone' => '0244000002']);

    for ($i = 0; $i < 3; $i++) {
        $this->post('/login/otp', ['phone' => '0244000001']);
    }

    $this->post('/login/otp', ['phone' => '0244000002'])->assertRedirect('/verify-otp');
});

test('one ip is capped at ten otp login requests even across many phones', function () {
    $last = null;

    for ($i = 1; $i <= 11; $i++) {
        $phone = '+2332440001' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        User::factory()->create(['phone' => $phone]);
        $last = $this->post('/login/otp', ['phone' => $phone]);
    }

    $last->assertStatus(429);
});

test('an unknown phone still counts toward the limit', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->post('/login/otp', ['phone' => '0249999999']);
    }

    $this->post('/login/otp', ['phone' => '0249999999'])->assertStatus(429);
});

test('a fourth resend for the same number within an hour is blocked', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->withSession(pendingFor('0244000001'))->post('/resend-otp');
    }

    $this->withSession(pendingFor('0244000001'))->post('/resend-otp')->assertStatus(429);
});

test('a blocked resend sends no sms', function () {
    for ($i = 0; $i < 4; $i++) {
        $this->withSession(pendingFor('0244000001'))->post('/resend-otp');
    }

    expect(smsCountTo('0244000001'))->toBe(3);
});

test('one ip is capped at ten resends even across many numbers', function () {
    $last = null;

    for ($i = 1; $i <= 11; $i++) {
        $phone = '+2332440002' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $last  = $this->withSession(pendingFor($phone))->post('/resend-otp');
    }

    $last->assertStatus(429);
});
