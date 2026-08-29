<?php

use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('it creates an admin', function () {
    $this->artisan('admin:create', [
        'surname' => 'Mensah',
        'first_name' => 'Kofi',
        'phone' => '0244445566',
    ])->assertSuccessful();

    $user = User::where('phone', '0244445566')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('admin'))->toBeTrue();
});

// nothing secret is typed into a terminal, so the account starts with no password
test('the new admin has no password', function () {
    $this->artisan('admin:create', [
        'surname' => 'Mensah',
        'first_name' => 'Kofi',
        'phone' => '0244445566',
    ]);

    expect(User::where('phone', '0244445566')->first()->password)->toBeNull();
});

test('it sends an invitation code', function () {
    $this->artisan('admin:create', [
        'surname' => 'Mensah',
        'first_name' => 'Kofi',
        'phone' => '0244445566',
    ]);

    expect(OtpCode::where('identifier', '0244445566')
        ->where('type', 'invitation')
        ->exists())->toBeTrue();
});

test('it stores the phone in one spelling', function () {
    $this->artisan('admin:create', [
        'surname' => 'Mensah',
        'first_name' => 'Kofi',
        'phone' => '+233 24 444 5566',
    ]);

    expect(User::where('phone', '0244445566')->exists())->toBeTrue();
});

test('it refuses a phone that already has an account', function () {
    User::factory()->create(['phone' => '0244445566']);

    $this->artisan('admin:create', [
        'surname' => 'Mensah',
        'first_name' => 'Kofi',
        'phone' => '0244445566',
    ])->assertFailed();

    expect(User::where('phone', '0244445566')->count())->toBe(1);
});

test('it takes an optional email', function () {
    $this->artisan('admin:create', [
        'surname' => 'Mensah',
        'first_name' => 'Kofi',
        'phone' => '0244445566',
        '--email' => 'kofi@example.com',
    ]);

    expect(User::where('phone', '0244445566')->first()->email)->toBe('kofi@example.com');
});

test('the new admin is active', function () {
    $this->artisan('admin:create', [
        'surname' => 'Mensah',
        'first_name' => 'Kofi',
        'phone' => '0244445566',
    ]);

    expect(User::where('phone', '0244445566')->first()->is_active)->toBeTrue();
});
