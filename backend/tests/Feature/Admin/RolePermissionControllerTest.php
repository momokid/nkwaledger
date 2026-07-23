<?php

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->authorizedAdmin = User::factory()->create();
    $this->authorizedAdmin->assignRole('admin');
    $this->authorizedAdmin->givePermissionTo('access-control.manage');
});

test('a guest is redirected to login', function () {
    $response = $this->get('/admin/permissions/roles');

    $response->assertRedirect('/login');
});

test('an admin without access-control.manage is forbidden', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/permissions/roles');

    $response->assertForbidden();
});

test('a non-admin with access-control.manage is still forbidden', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');
    $farmer->givePermissionTo('access-control.manage');

    $response = $this->actingAs($farmer)->get('/admin/permissions/roles');

    $response->assertForbidden();
});

test('an admin with access-control.manage can view the role matrix', function () {
    $response = $this->actingAs($this->authorizedAdmin)->get('/admin/permissions/roles');

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/Permissions/Roles')
            ->has('roles')
            ->has('modules')
            ->has('standalone')
    );
});

test('a role\'s permissions can be updated', function () {
    $role = Role::where('name', 'vet')->first();
    $permission = Permission::where('name', 'farm-types.view')->first();

    $response = $this->actingAs($this->authorizedAdmin)->put("/admin/permissions/roles/{$role->id}", [
        'permission_ids' => [$permission->id],
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($role->fresh()->hasPermissionTo('farm-types.view'))->toBeTrue();
});

test('removing a permission that would eliminate its last holder is blocked', function () {
    Role::where('name', 'admin')->first()->revokePermissionTo('farm-types.view');

    $role = Role::where('name', 'vet')->first();
    $role->givePermissionTo('farm-types.view');

    $onlyHolder = User::factory()->create();
    $onlyHolder->assignRole('vet');

    $response = $this->actingAs($this->authorizedAdmin)->put("/admin/permissions/roles/{$role->id}", [
        'permission_ids' => [],
    ]);

    $response->assertSessionHasErrors('permission_ids');
    expect($role->fresh()->hasPermissionTo('farm-types.view'))->toBeTrue();
});

test('a nonexistent permission id is rejected', function () {
    $role = Role::where('name', 'vet')->first();

    $response = $this->actingAs($this->authorizedAdmin)->put("/admin/permissions/roles/{$role->id}", [
        'permission_ids' => [999999],
    ]);

    $response->assertSessionHasErrors('permission_ids.0');
});

test('a non-numeric permission id is rejected', function () {
    $role = Role::where('name', 'vet')->first();

    $response = $this->actingAs($this->authorizedAdmin)->put("/admin/permissions/roles/{$role->id}", [
        'permission_ids' => ['not-a-real-permission'],
    ]);

    $response->assertSessionHasErrors('permission_ids.0');
});
