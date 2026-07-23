<?php

use App\Models\User;

test('a fresh password confirmation gets silently extended on any authenticated request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    session(['auth.password_confirmed_at' => now()->subMinutes(25)->timestamp]);

    $this->get('/dashboard');

    expect(session('auth.password_confirmed_at'))->toBeGreaterThan(now()->subSeconds(5)->timestamp);
});

test('an already expired password confirmation is not silently revived', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $expiredTimestamp = now()->subMinutes(75)->timestamp;
    session(['auth.password_confirmed_at' => $expiredTimestamp]);

    $this->get('/dashboard');

    expect(session('auth.password_confirmed_at'))->toBe($expiredTimestamp);
});

test('a user who has never confirmed their password does not get one silently created', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/dashboard');

    expect(session()->has('auth.password_confirmed_at'))->toBeFalse();
});

test('a guest request passes through without error', function () {
    $response = $this->get('/login');

    $response->assertOk();
});
