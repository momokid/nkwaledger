<?php

use App\Models\User;

test('it shares whether the phone is verified', function () {
    $user = User::factory()->verified()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->where('auth.user.is_phone_verified', true));
});

test('an unverified user is marked unverified', function () {
    $user = User::factory()->unverified()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->where('auth.user.is_phone_verified', false));
});

test('the login threshold never reaches the browser', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->missing('auth.user.verification_login_threshold'));
});

test('the login counter never reaches the browser', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->missing('auth.user.logins_since_verification'));
});

test('a guest shares no user', function () {
    $this->get('/')->assertInertia(fn($page) => $page->where('auth.user', null));
});
