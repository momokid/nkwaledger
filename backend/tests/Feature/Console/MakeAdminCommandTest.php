<?php

use App\Models\User;
use Spatie\Permission\Models\Permission; // added: needed to test access-control.manage behavior

// added: access-control.manage must exist before any test runs, since the command checks it every time
beforeEach(function () {
    Permission::firstOrCreate(['name' => 'access-control.manage', 'guard_name' => 'web']);
});

test('creates an admin user with valid input', function () {
    $this->artisan('make:admin')
        ->expectsQuestion('Surname', 'Mensah')
        ->expectsQuestion('First name', 'Kwame')
        ->expectsQuestion('Other name (optional)', null)
        ->expectsQuestion('Phone number', '+233244000099')
        ->expectsQuestion('Email (optional)', 'admin@nkwaledger.com')
        ->expectsQuestion('Password', 'Password@123')
        ->expectsQuestion('Confirm password', 'Password@123')
        ->expectsConfirmation("Should this admin be able to manage other users' roles and permissions?", 'yes') // added
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
        ->expectsConfirmation("Should this admin be able to manage other users' roles and permissions?", 'yes') // added
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

// added: bootstrap safety net tests for access-control.manage
test('the first admin is automatically granted access-control.manage even when declined', function () {
    $this->artisan('make:admin')
        ->expectsQuestion('Surname', 'Mensah')
        ->expectsQuestion('First name', 'Kwame')
        ->expectsQuestion('Other name (optional)', null)
        ->expectsQuestion('Phone number', '+233244000095')
        ->expectsQuestion('Email (optional)', null)
        ->expectsQuestion('Password', 'Password@123')
        ->expectsQuestion('Confirm password', 'Password@123')
        ->expectsConfirmation("Should this admin be able to manage other users' roles and permissions?", 'no')
        ->assertExitCode(0);

    $user = User::where('phone', '+233244000095')->first();

    expect($user->hasPermissionTo('access-control.manage'))->toBeTrue();
});

test('a new admin can decline access-control.manage when a holder already exists', function () {
    $permission = Permission::where('name', 'access-control.manage')->first();
    $existingHolder = User::factory()->create();
    $existingHolder->givePermissionTo($permission);

    $this->artisan('make:admin')
        ->expectsQuestion('Surname', 'Boateng')
        ->expectsQuestion('First name', 'Ama')
        ->expectsQuestion('Other name (optional)', null)
        ->expectsQuestion('Phone number', '+233244000094')
        ->expectsQuestion('Email (optional)', null)
        ->expectsQuestion('Password', 'Password@123')
        ->expectsQuestion('Confirm password', 'Password@123')
        ->expectsConfirmation("Should this admin be able to manage other users' roles and permissions?", 'no')
        ->assertExitCode(0);

    $user = User::where('phone', '+233244000094')->first();

    expect($user->hasPermissionTo('access-control.manage'))->toBeFalse();
});

test('an admin can be granted access-control.manage when requested', function () {
    $this->artisan('make:admin')
        ->expectsQuestion('Surname', 'Osei')
        ->expectsQuestion('First name', 'Kojo')
        ->expectsQuestion('Other name (optional)', null)
        ->expectsQuestion('Phone number', '+233244000093')
        ->expectsQuestion('Email (optional)', null)
        ->expectsQuestion('Password', 'Password@123')
        ->expectsQuestion('Confirm password', 'Password@123')
        ->expectsConfirmation("Should this admin be able to manage other users' roles and permissions?", 'yes')
        ->assertExitCode(0);

    $user = User::where('phone', '+233244000093')->first();

    expect($user->hasPermissionTo('access-control.manage'))->toBeTrue();
});
