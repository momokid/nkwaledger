<?php

use App\Models\User;

beforeEach(function () {
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(Database\Seeders\PermissionsSeeder::class);
});

function admin(bool $verified): User
{
    $user = $verified
        ? User::factory()->create()
        : User::factory()->unverified()->create();

    $user->assignRole('admin');
    $user->givePermissionTo('ledger-accounts.view', 'farm-types.view', 'access-control.manage');

    return $user;
}

test('an unverified admin cannot reach the ledger accounts page', function () {
    $this->actingAs(admin(false))
        ->get('/admin/ledger-accounts')
        ->assertRedirect('/admin/dashboard');
});

test('a verified admin can reach the ledger accounts page', function () {
    $this->actingAs(admin(true))
        ->get('/admin/ledger-accounts')
        ->assertOk();
});

test('an unverified admin cannot create a ledger account', function () {
    $this->actingAs(admin(false))
        ->post('/admin/ledger-accounts', ['name' => 'Cash'])
        ->assertRedirect('/admin/dashboard');
});

test('an unverified admin cannot reach farm types', function () {
    $this->actingAs(admin(false))
        ->get('/admin/farm-types')
        ->assertRedirect('/admin/dashboard');
});

test('an unverified admin cannot reach roles and permissions', function () {
    $this->actingAs(admin(false))
        ->get('/admin/permissions/roles')
        ->assertRedirect('/admin/dashboard');
});

test('an unverified admin still reaches their dashboard', function () {
    $this->actingAs(admin(false))
        ->get('/admin/dashboard')
        ->assertOk();
});

test('an unverified user can still send themselves a code', function () {
    $this->actingAs(admin(false))
        ->post('/verify-phone/send')
        ->assertRedirect();
});

test('an unverified user can still log out', function () {
    $this->actingAs(admin(false))
        ->post('/logout')
        ->assertRedirect('/login');
});
