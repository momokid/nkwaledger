<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('farmer can register with phone', function () {
    $response = $this->post('/register', [
        'surname' => 'Mensah',
        'first_name' => 'Kwame',
        'other_name' => 'Asante',
        'phone' => '+233244000001',
        'email' => 'kwame@example.com',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    expect(User::where('phone', '+233244000001')->exists())->toBeTrue();
});

test('farmer is assigned farmer role on registration', function () {
    $this->post('/register', [
        'surname' => 'Mensah',
        'first_name' => 'Kwame',
        'phone' => '+233244000001',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    $user = User::where('phone', '+233244000001')->first();

    expect($user->hasRole('farmer'))->toBeTrue();
});

test('otp is triggered after registration', function () {
    $this->post('/register', [
        'surname' => 'Mensah',
        'first_name' => 'Kwame',
        'phone' => '+233244000001',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    $this->assertDatabaseHas('otp_codes', [
        'identifier' => '+233244000001',
        'type' => 'registration',
    ]);
});

test('user is redirected to otp verification after registration', function () {
    $response = $this->post('/register', [
        'surname' => 'Mensah',
        'first_name' => 'Kwame',
        'phone' => '+233244000001',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    $response->assertRedirect('/verify-otp');
});

test('registration fails without phone', function () {
    $response = $this->post('/register', [
        'surname' => 'Mensah',
        'first_name' => 'Kwame',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    $response->assertSessionHasErrors(['phone']);
});

test('registration fails without surname', function () {
    $response = $this->post('/register', [
        'first_name' => 'Kwame',
        'phone' => '+233244000001',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    $response->assertSessionHasErrors(['surname']);
});

test('registration fails without first name', function () {
    $response = $this->post('/register', [
        'surname' => 'Mensah',
        'phone' => '+233244000001',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    $response->assertSessionHasErrors(['first_name']);
});

test('registration fails without password', function () {
    $response = $this->post('/register', [
        'surname' => 'Mensah',
        'first_name' => 'Kwame',
        'phone' => '+233244000001',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('registration fails with duplicate phone', function () {
    User::factory()->create(['phone' => '+233244000001']);

    $response = $this->post('/register', [
        'surname' => 'Boateng',
        'first_name' => 'Ama',
        'phone' => '+233244000001',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    $response->assertSessionHasErrors(['phone']);
});

test('registration fails with weak password', function () {
    $response = $this->post('/register', [
        'surname'              => 'Mensah',
        'first_name'           => 'Kwame',
        'phone'                => '+233244000001',
        'password'             => 'abc12',
        'password_confirmation' => 'abc12',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('email is optional on registration', function () {
    $response = $this->post('/register', [
        'surname' => 'Mensah',
        'first_name' => 'Kwame',
        'phone' => '+233244000001',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    expect(User::where('phone', '+233244000001')->exists())->toBeTrue();
});
