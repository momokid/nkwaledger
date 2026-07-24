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

test('agents get view permission on all current modules by default', function () {
    $role = Role::where('name', 'agent')->first();

    expect($role->hasPermissionTo('farm-types.view'))->toBeTrue();
    expect($role->hasPermissionTo('farmer-groups.view'))->toBeTrue();
    expect($role->hasPermissionTo('ledger-accounts.view'))->toBeTrue();
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
