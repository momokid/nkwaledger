<?php

use App\Models\User;
use App\Services\NavigationAccessService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->service = app(NavigationAccessService::class);
});

it('returns nothing for a guest', function () {
    expect($this->service->allowedRouteNames(null))->toBe([]);
});

it('includes a route the user holds the permission for', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $names = $this->service->allowedRouteNames($user);

    expect($names)->toContain('admin.ledger-accounts.index');
});

it('excludes a route the user has no permission for', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $names = $this->service->allowedRouteNames($user);

    expect($names)->not->toContain('admin.farm-types.index');
});

it('excludes everything for a user with no permissions', function () {
    $user = User::factory()->create();

    $names = $this->service->allowedRouteNames($user);

    expect($names)->not->toContain('admin.ledger-accounts.index');
    expect($names)->not->toContain('admin.farm-types.index');
});

it('includes role gated routes only for that role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $agent = User::factory()->create();
    $agent->assignRole('agent');

    expect($this->service->allowedRouteNames($admin))->toContain('admin.dashboard');
    expect($this->service->allowedRouteNames($agent))->not->toContain('admin.dashboard');
});

it('lists only routes that can be opened in a browser', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create']);

    $names = $this->service->allowedRouteNames($user);

    expect($names)->toContain('admin.ledger-accounts.index');
    expect($names)->not->toContain('admin.ledger-accounts.store');
});

it('never returns a permission name', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $names = $this->service->allowedRouteNames($user);

    foreach ($names as $name) {
        expect(str_starts_with($name, 'admin.'))->toBeTrue();
    }
});
