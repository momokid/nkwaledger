<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('user can login with phone and password', function () {
    $user = User::factory()->create([
        'phone'    => '+233244000001',
        'password' => bcrypt('Password@123'),
    ]);

    $response = $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'Password@123',
    ]);

    $response->assertRedirect('/verify-otp');
});

test('user can login with email and password', function () {
    $user = User::factory()->withEmail()->create([
        'email'    => 'kwame@example.com',
        'password' => bcrypt('Password@123'),
    ]);

    $response = $this->post('/login', [
        'identifier' => 'kwame@example.com',
        'password'   => 'Password@123',
    ]);

    $response->assertRedirect('/verify-otp');
});

test('session is not authenticated immediately after login credentials pass', function () {
    User::factory()->create([
        'phone'    => '+233244000001',
        'password' => bcrypt('Password@123'),
    ]);

    $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'Password@123',
    ]);

    $this->assertGuest();
});

test('otp is triggered after successful credential check', function () {
    User::factory()->create([
        'phone'    => '+233244000001',
        'password' => bcrypt('Password@123'),
    ]);

    $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'Password@123',
    ]);

    $this->assertDatabaseHas('otp_codes', [
        'identifier' => '+233244000001',
        'type'       => 'login',
    ]);
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

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', [
            'identifier' => '+233244000001',
            'password'   => 'WrongPassword',
        ]);
    }

    $response = $this->post('/login', [
        'identifier' => '+233244000001',
        'password'   => 'Password@123',
    ]);

    $response->assertSessionHasErrors(['identifier']);
});

test('user can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/login');
});
