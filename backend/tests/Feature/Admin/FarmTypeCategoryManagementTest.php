<?php

use App\Models\FarmTypeCategory;
use App\Models\User;
use App\Models\UserPermissionDenial;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "farm-type-categories.{$action}", 'guard_name' => 'web']);
    }
});

test('a guest is redirected to login when visiting farm type categories', function () {
    $this->get('/admin/farm-type-categories')->assertRedirect('/login');
});

test('a user without farm-type-categories.view cannot view the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/farm-type-categories')->assertForbidden();
});

test('an admin who has been explicitly denied farm-type-categories.view cannot view the list', function () {
    $denier = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('farm-type-categories.view');

    UserPermissionDenial::create([
        'user_id' => $admin->id,
        'permission_id' => Permission::where('name', 'farm-type-categories.view')->value('id'),
        'denied_by' => $denier->id,
    ]);

    $this->actingAs($admin)->get('/admin/farm-type-categories')->assertForbidden();
});

test('a user without farm-type-categories.create cannot create a category', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('farm-type-categories.view');

    $this->actingAs($user)->post('/admin/farm-type-categories', [
        'name' => 'Aquatic',
    ])->assertForbidden();
});

test('a user with farm-type-categories.create can create a category', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['farm-type-categories.view', 'farm-type-categories.create']);

    $this->actingAs($user)->post('/admin/farm-type-categories', [
        'name' => 'Aquatic',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('farm_type_categories', [
        'name' => 'Aquatic',
    ]);
});

test('creating a category with a duplicate name fails validation', function () {
    FarmTypeCategory::create(['name' => 'Crop']);

    $user = User::factory()->create();
    $user->givePermissionTo(['farm-type-categories.view', 'farm-type-categories.create']);

    $this->actingAs($user)->post('/admin/farm-type-categories', [
        'name' => 'Crop',
    ])->assertSessionHasErrors('name');
});

test('a user without farm-type-categories.update cannot update a category', function () {
    $category = FarmTypeCategory::create(['name' => 'Crop']);
    $user = User::factory()->create();
    $user->givePermissionTo('farm-type-categories.view');

    $this->actingAs($user)->put("/admin/farm-type-categories/{$category->id}", [
        'name' => 'Updated Name',
        'is_active' => false,
    ])->assertForbidden();
});

test('a user with farm-type-categories.update can update a category, including toggling is_active', function () {
    $category = FarmTypeCategory::create(['name' => 'Crop', 'is_active' => true]);
    $user = User::factory()->create();
    $user->givePermissionTo(['farm-type-categories.view', 'farm-type-categories.update']);

    $this->actingAs($user)->put("/admin/farm-type-categories/{$category->id}", [
        'name' => 'Updated Name',
        'is_active' => false,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('farm_type_categories', [
        'id' => $category->id,
        'name' => 'Updated Name',
        'is_active' => false,
    ]);
});

test('a user without farm-type-categories.delete cannot delete a category', function () {
    $category = FarmTypeCategory::create(['name' => 'Crop']);
    $user = User::factory()->create();
    $user->givePermissionTo('farm-type-categories.view');

    $this->actingAs($user)->delete("/admin/farm-type-categories/{$category->id}")->assertForbidden();
});

test('a user with farm-type-categories.delete can soft delete a category', function () {
    $category = FarmTypeCategory::create(['name' => 'Crop']);
    $user = User::factory()->create();
    $user->givePermissionTo(['farm-type-categories.view', 'farm-type-categories.delete']);

    $this->actingAs($user)->delete("/admin/farm-type-categories/{$category->id}")
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertSoftDeleted('farm_type_categories', ['id' => $category->id]);
});

test('a soft-deleted category is excluded from the default index', function () {
    FarmTypeCategory::create(['name' => 'Crop']);
    $deleted = FarmTypeCategory::create(['name' => 'Aquatic']);
    $deleted->delete();

    $user = User::factory()->create();
    $user->givePermissionTo('farm-type-categories.view');

    $response = $this->actingAs($user)->get('/admin/farm-type-categories');

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Crop')
    );
});

test('a user with farm-type-categories.view granted directly can view the list', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('farm-type-categories.view');

    $response = $this->actingAs($user)->get('/admin/farm-type-categories');

    $response->assertOk();
    // confirms the frontend receives an accurate view of what this specific user can do, not just that the page loaded
    $response->assertInertia(
        fn($page) => $page
            ->where('permissions.create', false)
            ->where('permissions.update', false)
            ->where('permissions.delete', false)
    );
});

test('the permissions prop reflects all four permissions when fully granted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'farm-type-categories.view',
        'farm-type-categories.create',
        'farm-type-categories.update',
        'farm-type-categories.delete',
    ]);

    $response = $this->actingAs($user)->get('/admin/farm-type-categories');

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->where('permissions.create', true)
            ->where('permissions.update', true)
            ->where('permissions.delete', true)
    );
});
