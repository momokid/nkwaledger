<?php

use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

test('a permission exists for every module action', function () {
    // walks the new nested config shape the same way the seeder does
    foreach (config('permissions.modules') as $module => $moduleConfig) {
        foreach (array_keys($moduleConfig['actions']) as $action) {
            expect(Permission::where('name', "{$module}.{$action}")->exists())->toBeTrue();
        }
    }
});

test('the access-control.manage permission exists', function () {
    expect(Permission::where('name', 'access-control.manage')->exists())->toBeTrue();
});

// agents keep farm-types.view (role:admin, not this permission, is what keeps them out of
// the admin pages), and get their own narrower permissions for KYC submission and for
// looking up the groups their farmers belong to - never the admin ones
test('agents keep farm-types.view and get their own KYC and group permissions', function () {
    $role = Role::where('name', 'agent')->first();

    expect($role->hasPermissionTo('farm-types.view'))->toBeTrue();
    expect($role->hasPermissionTo('farmers.kyc-submit'))->toBeTrue();
    expect($role->hasPermissionTo('farmer-groups.view-own'))->toBeTrue();

    expect($role->hasPermissionTo('farmers.view'))->toBeTrue();
    expect($role->hasPermissionTo('farm-units.view'))->toBeTrue();
    expect($role->hasPermissionTo('transactions.view'))->toBeTrue();
    expect($role->hasPermissionTo('approvals.view'))->toBeTrue();
});

test('agents never get the admin-only versions of those permissions', function () {
    $role = Role::where('name', 'agent')->first();

    expect($role->hasPermissionTo('farmers.verify'))->toBeFalse();
    expect($role->hasPermissionTo('farmer-groups.view'))->toBeFalse();
    expect($role->hasPermissionTo('ledger-accounts.view'))->toBeFalse();
});

test('admin keeps approval and management permissions but does not get the agent-only ones', function () {
    $role = Role::where('name', 'admin')->first();

    expect($role->hasPermissionTo('farmers.verify'))->toBeTrue();
    expect($role->hasPermissionTo('farmer-groups.view'))->toBeTrue();
    expect($role->hasPermissionTo('farm-types.create'))->toBeTrue();
    expect($role->hasPermissionTo('farm-types.update'))->toBeTrue();
    expect($role->hasPermissionTo('farm-types.delete'))->toBeTrue();

    expect($role->hasPermissionTo('farmers.kyc-submit'))->toBeFalse();
    expect($role->hasPermissionTo('farmer-groups.view-own'))->toBeFalse();
});

test('agents do not get create, update, or delete permissions by default', function () {
    $role = Role::where('name', 'agent')->first();

    expect($role->hasPermissionTo('farm-types.create'))->toBeFalse();
    expect($role->hasPermissionTo('farm-types.update'))->toBeFalse();
    expect($role->hasPermissionTo('farm-types.delete'))->toBeFalse();
});

test('no role has access-control.manage by default', function () {
    foreach (Role::all() as $role) {
        expect($role->hasPermissionTo('access-control.manage'))->toBeFalse();
    }
});

test('running the seeder twice does not create duplicate permissions', function () {
    $this->seed(PermissionsSeeder::class);

    expect(Permission::where('name', 'farm-types.view')->count())->toBe(1);
});
