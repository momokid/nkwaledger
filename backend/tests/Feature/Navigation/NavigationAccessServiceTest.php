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

// each role group carries its own sidebar, so the agent addresses are listed too
it('includes agent routes for an agent', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    expect($this->service->allowedRouteNames($agent))->toContain('agent.farmers.index');
});

it('excludes agent routes from someone without the permission', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    expect($this->service->allowedRouteNames($vet))->not->toContain('agent.farmers.index');
});

it('never returns a permission name', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $names = $this->service->allowedRouteNames($user);

    foreach ($names as $name) {
        $known = str_starts_with($name, 'admin.')
            || str_starts_with($name, 'agent.')
            || str_starts_with($name, 'my-records.');

        expect($known)->toBeTrue();
    }
});

// the farmer's own pages carry a sidebar too
it('includes the farmer record pages for a farmer', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $names = $this->service->allowedRouteNames($farmer);

    expect($names)->toContain('my-records.index');
    expect($names)->toContain('my-records.create');
});

it('excludes the farmer record pages from someone without the permission', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    expect($this->service->allowedRouteNames($vet))->not->toContain('my-records.index');
});
