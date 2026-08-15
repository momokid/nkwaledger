<?php

use App\Contracts\SmsProvider;
use App\Models\User;

function smsCountTo(string $phone): int
{
    return collect(app(SmsProvider::class)->sent)->where('phone', $phone)->count();
}

test('a fourth otp login request for the same phone within an hour is blocked', function () {
    User::factory()->create(['phone' => '+233244000001']);

    for ($i = 0; $i < 3; $i++) {
        $this->post('/login/otp', ['phone' => '+233244000001']);
    }

    $response = $this->post('/login/otp', ['phone' => '+233244000001']);

    $response->assertStatus(429);
});

test('a blocked otp login request sends no sms', function () {
    User::factory()->create(['phone' => '+233244000001']);

    for ($i = 0; $i < 4; $i++) {
        $this->post('/login/otp', ['phone' => '+233244000001']);
    }

    expect(smsCountTo('+233244000001'))->toBe(3);
});

test('a different phone from the same ip is still allowed', function () {
    User::factory()->create(['phone' => '+233244000001']);
    User::factory()->create(['phone' => '+233244000002']);

    for ($i = 0; $i < 3; $i++) {
        $this->post('/login/otp', ['phone' => '+233244000001']);
    }

    $response = $this->post('/login/otp', ['phone' => '+233244000002']);

    $response->assertRedirect('/verify-otp');
});

test('one ip is capped at ten otp login requests even across many phones', function () {
    for ($i = 1; $i <= 11; $i++) {
        $phone = '+2332440001' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        User::factory()->create(['phone' => $phone]);
    }

    $lastResponse = null;

    for ($i = 1; $i <= 11; $i++) {
        $phone = '+2332440001' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $lastResponse = $this->post('/login/otp', ['phone' => $phone]);
    }

    $lastResponse->assertStatus(429);
});

test('a fourth resend for the same number within an hour is blocked', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->post('/resend-otp', [
            'identifier' => '+233244000001',
            'type'       => 'login',
        ]);
    }

    $response = $this->post('/resend-otp', [
        'identifier' => '+233244000001',
        'type'       => 'login',
    ]);

    $response->assertStatus(429);
});

test('a blocked resend sends no sms', function () {
    for ($i = 0; $i < 4; $i++) {
        $this->post('/resend-otp', [
            'identifier' => '+233244000001',
            'type'       => 'login',
        ]);
    }

    expect(smsCountTo('+233244000001'))->toBe(3);
});

test('one ip is capped at ten resends even across many numbers', function () {
    $lastResponse = null;

    for ($i = 1; $i <= 11; $i++) {
        $lastResponse = $this->post('/resend-otp', [
            'identifier' => '+2332440002' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'type'       => 'login',
        ]);
    }

    $lastResponse->assertStatus(429);
});
