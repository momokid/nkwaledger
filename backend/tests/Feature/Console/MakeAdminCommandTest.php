<?php

use App\Models\User;

test('creates an admin user with valid input', function () {
    $this->artisan('make:admin')
        ->expectsQuestion('Surname', 'Mensah')
        ->expectsQuestion('First name', 'Kwame')
        ->expectsQuestion('Other name (optional)', null)
        ->expectsQuestion('Phone number', '+233244000099')
        ->expectsQuestion('Email (optional)', 'admin@nkwaledger.com')
        ->expectsQuestion('Password', 'Password@123')
        ->expectsQuestion('Confirm password', 'Password@123')
        ->assertExitCode(0);

    $user = User::where('phone', '+233244000099')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->is_active)->toBeTrue();
    expect($user->phone_verified_at)->not->toBeNull();
});

test('creates an admin without an email', function () {
    $this->artisan('make:admin')
        ->expectsQuestion('Surname', 'Boateng')
        ->expectsQuestion('First name', 'Ama')
        ->expectsQuestion('Other name (optional)', null)
        ->expectsQuestion('Phone number', '+233244000096')
        ->expectsQuestion('Email (optional)', null)
        ->expectsQuestion('Password', 'Password@123')
        ->expectsQuestion('Confirm password', 'Password@123')
        ->assertExitCode(0);

    $user = User::where('phone', '+233244000096')->first();

    expect($user->email)->toBeNull();
    expect($user->hasRole('admin'))->toBeTrue();
});

test('fails when phone number is already taken', function () {
    User::factory()->create(['phone' => '+233244000099']);

    $this->artisan('make:admin')
        ->expectsQuestion('Surname', 'Mensah')
        ->expectsQuestion('First name', 'Kwame')
        ->expectsQuestion('Other name (optional)', null)
        ->expectsQuestion('Phone number', '+233244000099')
        ->expectsQuestion('Email (optional)', null)
        ->expectsQuestion('Password', 'Password@123')
        ->expectsQuestion('Confirm password', 'Password@123')
        ->assertExitCode(1);
});

test('fails when passwords do not match', function () {
    $this->artisan('make:admin')
        ->expectsQuestion('Surname', 'Mensah')
        ->expectsQuestion('First name', 'Kwame')
        ->expectsQuestion('Other name (optional)', null)
        ->expectsQuestion('Phone number', '+233244000098')
        ->expectsQuestion('Email (optional)', null)
        ->expectsQuestion('Password', 'Password@123')
        ->expectsQuestion('Confirm password', 'Different@123')
        ->assertExitCode(1);
});

test('fails when password is too weak', function () {
    $this->artisan('make:admin')
        ->expectsQuestion('Surname', 'Mensah')
        ->expectsQuestion('First name', 'Kwame')
        ->expectsQuestion('Other name (optional)', null)
        ->expectsQuestion('Phone number', '+233244000097')
        ->expectsQuestion('Email (optional)', null)
        ->expectsQuestion('Password', 'abc123')
        ->expectsQuestion('Confirm password', 'abc123')
        ->assertExitCode(1);
});
