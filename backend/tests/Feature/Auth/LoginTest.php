<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('login screen can be rendered', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
});

test('user can login with phone and password', function () {
    User::factory()->create([
        'phone' => '+233244000001',
        'password' => bcrypt('Password@123'),
        'is_phone_verified' => true,
    ]);

    $response = $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'Password@123',
    ]);

    $response->assertRedirect('/farmer/dashboard');
    $this->assertAuthenticated();
});

test('user can login with email and password', function () {
    User::factory()->withEmail()->create([
        'email' => 'kwame@example.com',
        'password' => bcrypt('Password@123'),
        'is_phone_verified' => true,
    ]);

    $response = $this->post('/login', [
        'identifier' => 'kwame@example.com',
        'password'   => 'Password@123',
    ]);

    $response->assertRedirect('/farmer/dashboard');
    $this->assertAuthenticated();
});

test('user is authenticated immediately after password login', function () {
    User::factory()->create([
        'phone'    => '+233244000001',
        'password' => bcrypt('Password@123'),
        'is_phone_verified' => true,
    ]);

    $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'Password@123',
    ]);

    $this->assertAuthenticated();
});

test('unverified user is redirected to verify otp on password login', function () {
    User::factory()->create([
        'phone'             => '+233244000001',
        'password'          => bcrypt('Password@123'),
        'is_phone_verified' => false,
    ]);

    $response = $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'Password@123',
    ]);

    $response->assertRedirect('/verify-otp');
    $this->assertGuest();
});

test('verified user is logged in directly on password login', function () {
    User::factory()->create([
        'phone'             => '+233244000001',
        'password'          => bcrypt('Password@123'),
        'is_phone_verified' => true,
    ]);

    $response = $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'Password@123',
    ]);

    $response->assertRedirect('/farmer/dashboard');
    $this->assertAuthenticated();
});

test('otp login sends otp to phone', function () {
    User::factory()->create([
        'phone' => '+233244000001',
    ]);

    $this->post('/login/otp', [
        'phone' => '+233244000001',
    ]);

    $this->assertDatabaseHas('otp_codes', [
        'identifier' => '+233244000001',
        'type'       => 'login',
    ]);
});

test('otp login redirects to verify otp', function () {
    User::factory()->create([
        'phone' => '+233244000001',
    ]);

    $response = $this->post('/login/otp', [
        'phone' => '+233244000001',
    ]);

    $response->assertRedirect('/verify-otp');
});

test('otp login with non-existent phone still redirects to verify otp', function () {
    $response = $this->post('/login/otp', [
        'phone' => '+233244000099',
    ]);

    $response->assertRedirect('/verify-otp');
});

test('login fails with wrong password', function () {
    User::factory()->create([
        'phone'    => '+233244000001',
        'password' => bcrypt('Password@123'),
    ]);

    $response = $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'WrongPassword@123',
    ]);

    $response->assertSessionHasErrors(['identifier']);
    $this->assertGuest();
});

test('login fails with non-existent phone', function () {
    $response = $this->post('/login', [
        'identifier' => '+233244000099',
        'password'   => 'Password@123',
    ]);

    $response->assertSessionHasErrors(['identifier']);
    $this->assertGuest();
});

test('inactive user cannot login', function () {
    User::factory()->create([
        'phone'     => '+233244000001',
        'password'  => bcrypt('Password@123'),
        'is_active' => false,
    ]);

    $response = $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'Password@123',
    ]);

    $response->assertSessionHasErrors(['identifier']);
    $this->assertGuest();
});

test('login is rate limited after five failed attempts', function () {
    User::factory()->create([
        'phone'    => '+233244000001',
        'password' => bcrypt('Password@123'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'identifier' => '+233244000001',
            'password'   => 'WrongPassword',
        ]);
    }

    $response = $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'WrongPassword',
    ]);

    $response->assertSessionHasErrors(['identifier']);
});

test('user can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
});
