<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->authorizedAdmin = User::factory()->create();
    $this->authorizedAdmin->assignRole('admin');
    $this->authorizedAdmin->givePermissionTo('access-control.manage');
});

test('updating a role without a recent password confirmation is redirected to confirm', function () {
    $role = Role::where('name', 'vet')->first();

    $response = $this->actingAs($this->authorizedAdmin)
        ->put("/admin/permissions/roles/{$role->id}", ['permission_ids' => []]);

    $response->assertRedirect('/confirm-password');
});

test('updating a role succeeds once the password has been confirmed', function () {
    $role = Role::where('name', 'vet')->first();

    session(['auth.password_confirmed_at' => now()->timestamp]);

    $response = $this->actingAs($this->authorizedAdmin)
        ->put("/admin/permissions/roles/{$role->id}", ['permission_ids' => []]);

    $response->assertSessionDoesntHaveErrors();
});

test('viewing the role matrix does not require password confirmation', function () {
    $response = $this->actingAs($this->authorizedAdmin)->get('/admin/permissions/roles');

    $response->assertOk();
});

test('viewing a user does not require password confirmation', function () {
    $target = User::factory()->create();

    $response = $this->actingAs($this->authorizedAdmin)->get("/admin/permissions/users/{$target->id}");

    $response->assertOk();
});
