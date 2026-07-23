<?php

use App\Models\User;
use App\Models\UserPermissionDenial;
use App\Services\AccessControlService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->service = new AccessControlService();
});

test('a user with a role that grants the permission can access it', function () {
    $user = User::factory()->create();
    $user->assignRole('agent');

    expect($this->service->can($user, 'farm-types.view'))->toBeTrue();
});

test('a user without the permission cannot access it', function () {
    $user = User::factory()->create();
    $user->assignRole('agent');

    expect($this->service->can($user, 'farm-types.delete'))->toBeFalse();
});

test('a direct grant on the user allows access even without the role providing it', function () {
    $user = User::factory()->create();
    $user->assignRole('agent');
    $user->givePermissionTo('farm-types.delete');

    expect($this->service->can($user, 'farm-types.delete'))->toBeTrue();
});

test('a denial blocks access even though the role grants the permission', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('agent');

    $permission = Permission::where('name', 'farm-types.view')->first();

    UserPermissionDenial::create([
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]);

    expect($this->service->can($user, 'farm-types.view'))->toBeFalse();
});

test('a denial blocks access even when the permission was granted directly', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('farm-types.view');

    $permission = Permission::where('name', 'farm-types.view')->first();

    UserPermissionDenial::create([
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]);

    expect($this->service->can($user, 'farm-types.view'))->toBeFalse();
});

test('a permission name that does not exist simply returns false', function () {
    $user = User::factory()->create();
    $user->assignRole('agent');

    expect($this->service->can($user, 'nonexistent.permission'))->toBeFalse();
});

// added: tests for the last-holder safety check
test('isLastHolder is true when exactly one user effectively holds the permission', function () {
    $permission = Permission::firstOrCreate(['name' => 'access-control.manage', 'guard_name' => 'web']);
    $holder = User::factory()->create();
    $holder->givePermissionTo($permission);

    expect($this->service->isLastHolder($holder, 'access-control.manage'))->toBeTrue();
});

test('isLastHolder is false when another user also effectively holds the permission', function () {
    $permission = Permission::firstOrCreate(['name' => 'access-control.manage', 'guard_name' => 'web']);
    $holder = User::factory()->create();
    $holder->givePermissionTo($permission);
    $otherHolder = User::factory()->create();
    $otherHolder->givePermissionTo($permission);

    expect($this->service->isLastHolder($holder, 'access-control.manage'))->toBeFalse();
});

test('isLastHolder is false for a user who does not hold the permission at all', function () {
    Permission::firstOrCreate(['name' => 'access-control.manage', 'guard_name' => 'web']);
    $user = User::factory()->create();

    expect($this->service->isLastHolder($user, 'access-control.manage'))->toBeFalse();
});

test('a denied user does not count as the last holder, even if they are the only one with the grant', function () {
    $admin = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'access-control.manage', 'guard_name' => 'web']);
    $holder = User::factory()->create();
    $holder->givePermissionTo($permission);

    UserPermissionDenial::create([
        'user_id' => $holder->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]);

    expect($this->service->isLastHolder($holder, 'access-control.manage'))->toBeFalse();
});

test('a role holder counts toward effective holders, not just direct grants', function () {
    $permission = Permission::firstOrCreate(['name' => 'access-control.manage', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $roleHolder = User::factory()->create();
    $roleHolder->assignRole('admin');

    $directHolder = User::factory()->create();
    $directHolder->givePermissionTo($permission);

    expect($this->service->isLastHolder($roleHolder, 'access-control.manage'))->toBeFalse();
    expect($this->service->isLastHolder($directHolder, 'access-control.manage'))->toBeFalse();
});


// added: tests for role-level removal safety check
test('removing the permission from a role is blocked when it would leave no holders', function () {
    $permission = Permission::firstOrCreate(['name' => 'access-control.manage', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $onlyHolder = User::factory()->create();
    $onlyHolder->assignRole('admin');

    expect($this->service->roleRemovalWouldEliminateLastHolder($role, 'access-control.manage'))->toBeTrue();
});

test('removing the permission from a role is allowed when a holder has a direct grant too', function () {
    $permission = Permission::firstOrCreate(['name' => 'access-control.manage', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $holder = User::factory()->create();
    $holder->assignRole('admin');
    $holder->givePermissionTo($permission);

    expect($this->service->roleRemovalWouldEliminateLastHolder($role, 'access-control.manage'))->toBeFalse();
});

test('removing the permission from a role is allowed when another role also grants it', function () {
    $permission = Permission::firstOrCreate(['name' => 'access-control.manage', 'guard_name' => 'web']);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $agentRole = Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
    $adminRole->givePermissionTo($permission);
    $agentRole->givePermissionTo($permission);

    $holder = User::factory()->create();
    $holder->assignRole(['admin', 'agent']);

    expect($this->service->roleRemovalWouldEliminateLastHolder($adminRole, 'access-control.manage'))->toBeFalse();
});

test('removing the permission from a role that does not have it returns false', function () {
    Permission::firstOrCreate(['name' => 'access-control.manage', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);

    expect($this->service->roleRemovalWouldEliminateLastHolder($role, 'access-control.manage'))->toBeFalse();
});
