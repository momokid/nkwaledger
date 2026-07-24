<?php

use App\Models\User;
use App\Models\UserPermissionDenial;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    Route::middleware(['web', 'auth', 'access:farm-types.view'])
        ->get('/__test/farm-types-protected', fn() => 'ok');
});

test('a guest is redirected to login', function () {
    $response = $this->get('/__test/farm-types-protected');

    $response->assertRedirect('/login');
});

test('a user without the permission is forbidden', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $response = $this->actingAs($user)->get('/__test/farm-types-protected');

    $response->assertForbidden();
});

test('a user with the permission via their role is allowed through', function () {
    $user = User::factory()->create();
    $user->assignRole('agent');

    $response = $this->actingAs($user)->get('/__test/farm-types-protected');

    $response->assertOk();
});

test('a user denied the permission is forbidden even though their role grants it', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('agent');

    $permission = Permission::where('name', 'farm-types.view')->first();

    UserPermissionDenial::create([
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]);

    $response = $this->actingAs($user)->get('/__test/farm-types-protected');

    $response->assertForbidden();
});
