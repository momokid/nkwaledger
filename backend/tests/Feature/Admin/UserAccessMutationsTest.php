<?php

use App\Models\User;
use App\Models\UserPermissionDenial;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->authorizedAdmin = User::factory()->create();
    $this->authorizedAdmin->assignRole('admin');
    $this->authorizedAdmin->givePermissionTo('access-control.manage');

    session(['auth.password_confirmed_at' => now()->timestamp]);
});

test('an admin can change a user role', function () {
    $target = User::factory()->create();
    $target->assignRole('farmer');

    $response = $this->actingAs($this->authorizedAdmin)->put("/admin/permissions/users/{$target->id}/role", [
        'role' => 'agent',
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($target->fresh()->hasRole('agent'))->toBeTrue();
    expect($target->fresh()->hasRole('farmer'))->toBeFalse();
});

test('changing a user role is blocked when it would eliminate the last holder of a permission', function () {
    // isolates a permission to only the agent role, so the target becomes its sole holder
    Role::where('name', 'admin')->first()->revokePermissionTo('ledger-accounts.delete');
    Role::where('name', 'agent')->first()->givePermissionTo('ledger-accounts.delete');

    $target = User::factory()->create();
    $target->assignRole('agent');

    $response = $this->actingAs($this->authorizedAdmin)->put("/admin/permissions/users/{$target->id}/role", [
        'role' => 'farmer',
    ]);

    $response->assertSessionHasErrors('role');
    expect($target->fresh()->hasRole('agent'))->toBeTrue();
});

test('changing to a nonexistent role is rejected', function () {
    $target = User::factory()->create();
    $target->assignRole('farmer');

    $response = $this->actingAs($this->authorizedAdmin)->put("/admin/permissions/users/{$target->id}/role", [
        'role' => 'super-farmer',
    ]);

    $response->assertSessionHasErrors('role');
});

test('an admin can grant a permission directly to a user', function () {
    $target = User::factory()->create();
    $target->assignRole('farmer');
    $permission = Permission::where('name', 'farm-types.view')->first();

    $response = $this->actingAs($this->authorizedAdmin)->post("/admin/permissions/users/{$target->id}/grants", [
        'permission_id' => $permission->id,
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($target->fresh()->hasDirectPermission($permission))->toBeTrue();
});

test('granting a permission clears an existing denial', function () {
    $target = User::factory()->create();
    $target->assignRole('agent');
    $permission = Permission::where('name', 'farm-types.view')->first();

    UserPermissionDenial::create([
        'user_id' => $target->id,
        'permission_id' => $permission->id,
        'denied_by' => $this->authorizedAdmin->id,
    ]);

    $this->actingAs($this->authorizedAdmin)->post("/admin/permissions/users/{$target->id}/grants", [
        'permission_id' => $permission->id,
    ]);

    expect(UserPermissionDenial::where('user_id', $target->id)->where('permission_id', $permission->id)->exists())
        ->toBeFalse();
});

test('an admin can revoke a direct grant', function () {
    $target = User::factory()->create();
    $target->assignRole('farmer');
    $permission = Permission::where('name', 'farm-types.view')->first();
    $target->givePermissionTo($permission);

    $response = $this->actingAs($this->authorizedAdmin)
        ->delete("/admin/permissions/users/{$target->id}/grants/{$permission->id}");

    $response->assertSessionDoesntHaveErrors();
    expect($target->fresh()->hasDirectPermission($permission))->toBeFalse();
});

test('revoking a grant is blocked when it would eliminate the last holder', function () {
    Role::where('name', 'admin')->first()->revokePermissionTo('ledger-accounts.delete');

    $target = User::factory()->create();
    $target->assignRole('farmer');
    $permission = Permission::where('name', 'ledger-accounts.delete')->first();
    $target->givePermissionTo($permission);

    $response = $this->actingAs($this->authorizedAdmin)
        ->delete("/admin/permissions/users/{$target->id}/grants/{$permission->id}");

    $response->assertSessionHasErrors('permission');
    expect($target->fresh()->hasDirectPermission($permission))->toBeTrue();
});

test('an admin can deny a permission for a user', function () {
    $target = User::factory()->create();
    $target->assignRole('agent');
    $permission = Permission::where('name', 'farm-types.view')->first();

    $response = $this->actingAs($this->authorizedAdmin)->post("/admin/permissions/users/{$target->id}/denials", [
        'permission_id' => $permission->id,
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect(UserPermissionDenial::where('user_id', $target->id)->where('permission_id', $permission->id)->exists())
        ->toBeTrue();
});

test('denying a permission clears an existing direct grant', function () {
    $target = User::factory()->create();
    $target->assignRole('farmer');
    $permission = Permission::where('name', 'farm-types.view')->first();
    $target->givePermissionTo($permission);

    $this->actingAs($this->authorizedAdmin)->post("/admin/permissions/users/{$target->id}/denials", [
        'permission_id' => $permission->id,
    ]);

    expect($target->fresh()->hasDirectPermission($permission))->toBeFalse();
});

test('denying a permission is blocked when it would eliminate the last holder', function () {
    Role::where('name', 'admin')->first()->revokePermissionTo('ledger-accounts.delete');
    Role::where('name', 'agent')->first()->givePermissionTo('ledger-accounts.delete');

    $target = User::factory()->create();
    $target->assignRole('agent');
    $permission = Permission::where('name', 'ledger-accounts.delete')->first();

    $response = $this->actingAs($this->authorizedAdmin)->post("/admin/permissions/users/{$target->id}/denials", [
        'permission_id' => $permission->id,
    ]);

    $response->assertSessionHasErrors('permission');
    expect(UserPermissionDenial::where('user_id', $target->id)->where('permission_id', $permission->id)->exists())
        ->toBeFalse();
});

test('an admin can remove a denial to restore default access', function () {
    $target = User::factory()->create();
    $target->assignRole('agent');
    $permission = Permission::where('name', 'farm-types.view')->first();

    UserPermissionDenial::create([
        'user_id' => $target->id,
        'permission_id' => $permission->id,
        'denied_by' => $this->authorizedAdmin->id,
    ]);

    $response = $this->actingAs($this->authorizedAdmin)
        ->delete("/admin/permissions/users/{$target->id}/denials/{$permission->id}");

    $response->assertSessionDoesntHaveErrors();
    expect(UserPermissionDenial::where('user_id', $target->id)->where('permission_id', $permission->id)->exists())
        ->toBeFalse();
});

test('a guest is redirected to login on the role update endpoint', function () {
    $target = User::factory()->create();

    $response = $this->put("/admin/permissions/users/{$target->id}/role", ['role' => 'agent']);

    $response->assertRedirect('/login');
});
