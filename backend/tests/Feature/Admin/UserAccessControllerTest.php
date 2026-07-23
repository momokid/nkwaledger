<?php

use App\Models\User;
use App\Models\UserPermissionDenial;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->authorizedAdmin = User::factory()->create();
    $this->authorizedAdmin->assignRole('admin');
    $this->authorizedAdmin->givePermissionTo('access-control.manage');
});

test('a guest is redirected to login on the search screen', function () {
    $response = $this->get('/admin/permissions/users');

    $response->assertRedirect('/login');
});

test('an admin without access-control.manage is forbidden on the search screen', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/permissions/users');

    $response->assertForbidden();
});

test('an authorized admin can view the search screen with no query', function () {
    $response = $this->actingAs($this->authorizedAdmin)->get('/admin/permissions/users');

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/Permissions/Users')
            ->has('results', 0)
    );
});

test('searching by phone returns a matching user', function () {
    $target = User::factory()->create(['phone' => '+233244000077']);

    $response = $this->actingAs($this->authorizedAdmin)->get('/admin/permissions/users?q=244000077');

    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/Permissions/Users')
            ->has('results', 1)
            ->where('results.0.id', $target->id)
    );
});

test('searching by email returns a matching user', function () {
    $target = User::factory()->create(['email' => 'kwame@nkwaledger.com']);

    $response = $this->actingAs($this->authorizedAdmin)->get('/admin/permissions/users?q=kwame@nkwaledger.com');

    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/Permissions/Users')
            ->has('results', 1)
            ->where('results.0.id', $target->id)
    );
});

test('a guest is redirected to login on a user detail page', function () {
    $target = User::factory()->create();

    $response = $this->get("/admin/permissions/users/{$target->id}");

    $response->assertRedirect('/login');
});

test('an admin without access-control.manage is forbidden on a user detail page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $target = User::factory()->create();

    $response = $this->actingAs($admin)->get("/admin/permissions/users/{$target->id}");

    $response->assertForbidden();
});

test('an authorized admin can view a user detail page', function () {
    $target = User::factory()->create();
    $target->assignRole('agent');

    $response = $this->actingAs($this->authorizedAdmin)->get("/admin/permissions/users/{$target->id}");

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/Permissions/UserDetail')
            ->where('user.id', $target->id)
            ->where('currentRole', 'agent')
            ->has('roles')
            ->has('modules')
            ->has('standalone')
    );
});

test('a permission with no override shows a default state', function () {
    $target = User::factory()->create();
    $target->assignRole('agent');

    $response = $this->actingAs($this->authorizedAdmin)->get("/admin/permissions/users/{$target->id}");

    $response->assertInertia(function ($page) {
        $modules = $page->toArray()['props']['modules'];
        $farmTypesView = collect($modules[0]['permissions'])->firstWhere('label', 'View');

        expect($farmTypesView['state'])->toBe('default');
    });
});

test('a directly granted permission shows a grant state', function () {
    $target = User::factory()->create();
    $target->assignRole('farmer');
    $target->givePermissionTo('farm-types.view');

    $response = $this->actingAs($this->authorizedAdmin)->get("/admin/permissions/users/{$target->id}");

    $response->assertInertia(function ($page) {
        $modules = $page->toArray()['props']['modules'];
        $farmTypesView = collect($modules[0]['permissions'])->firstWhere('label', 'View');

        expect($farmTypesView['state'])->toBe('grant');
    });
});

test('a denied permission shows a deny state', function () {
    $target = User::factory()->create();
    $target->assignRole('agent');

    $permission = Permission::where('name', 'farm-types.view')->first();

    UserPermissionDenial::create([
        'user_id' => $target->id,
        'permission_id' => $permission->id,
        'denied_by' => $this->authorizedAdmin->id,
    ]);

    $response = $this->actingAs($this->authorizedAdmin)->get("/admin/permissions/users/{$target->id}");

    $response->assertInertia(function ($page) {
        $modules = $page->toArray()['props']['modules'];
        $farmTypesView = collect($modules[0]['permissions'])->firstWhere('label', 'View');

        expect($farmTypesView['state'])->toBe('deny');
    });
});
