<?php

use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\User;
use App\Models\UserPermissionDenial;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "farm-types.{$action}", 'guard_name' => 'web']);
    }
});

test('a guest is redirected to login when visiting farm types', function () {
    $this->get('/admin/farm-types')->assertRedirect('/login');
});

test('a user without farm-types.view cannot view the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/farm-types')->assertForbidden();
});

test('a user with farm-types.view granted directly can view the list', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('farm-types.view');

    $this->actingAs($user)->get('/admin/farm-types')->assertOk();
});

test('an admin who has been explicitly denied farm-types.view cannot view the list', function () {
    $denier = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('farm-types.view');

    UserPermissionDenial::create([
        'user_id' => $admin->id,
        'permission_id' => Permission::where('name', 'farm-types.view')->value('id'),
        'denied_by' => $denier->id,
    ]);

    $this->actingAs($admin)->get('/admin/farm-types')->assertForbidden();
});

test('a user without farm-types.create cannot create a farm type', function () {
    $category = FarmTypeCategory::create(['name' => 'Crop']);
    $user = User::factory()->create();
    $user->givePermissionTo('farm-types.view');

    $this->actingAs($user)->post('/admin/farm-types', [
        'name' => 'Maize',
        'category_id' => $category->id,
    ])->assertForbidden();
});

test('a user with farm-types.create can create a farm type', function () {
    $category = FarmTypeCategory::create(['name' => 'Crop']);
    $user = User::factory()->create();
    $user->givePermissionTo(['farm-types.view', 'farm-types.create']);

    $this->actingAs($user)->post('/admin/farm-types', [
        'name' => 'Maize',
        'category_id' => $category->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('farm_types', [
        'name' => 'Maize',
        'category_id' => $category->id,
    ]);
});

test('a farm type can be created without a category', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['farm-types.view', 'farm-types.create']);

    $this->actingAs($user)->post('/admin/farm-types', [
        'name' => 'Uncategorized',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('farm_types', [
        'name' => 'Uncategorized',
        'category_id' => null,
    ]);
});

test('creating a farm type with a duplicate name fails validation', function () {
    FarmType::factory()->create(['name' => 'Maize']);

    $user = User::factory()->create();
    $user->givePermissionTo(['farm-types.view', 'farm-types.create']);

    $this->actingAs($user)->post('/admin/farm-types', [
        'name' => 'Maize',
    ])->assertSessionHasErrors('name');
});

test('creating a farm type with a non-existent category_id fails validation', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['farm-types.view', 'farm-types.create']);

    $this->actingAs($user)->post('/admin/farm-types', [
        'name' => 'Maize',
        'category_id' => 999,
    ])->assertSessionHasErrors('category_id');
});

test('a user without farm-types.update cannot update a farm type', function () {
    $farmType = FarmType::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('farm-types.view');

    $this->actingAs($user)->put("/admin/farm-types/{$farmType->id}", [
        'name' => 'Updated Name',
        'is_active' => false,
    ])->assertForbidden();
});

test('a user with farm-types.update can update a farm type, including toggling is_active', function () {
    $category = FarmTypeCategory::create(['name' => 'Livestock']);
    $farmType = FarmType::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    $user->givePermissionTo(['farm-types.view', 'farm-types.update']);

    $this->actingAs($user)->put("/admin/farm-types/{$farmType->id}", [
        'name' => 'Updated Name',
        'category_id' => $category->id,
        'is_active' => false,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('farm_types', [
        'id' => $farmType->id,
        'name' => 'Updated Name',
        'category_id' => $category->id,
        'is_active' => false,
    ]);
});

test('a user without farm-types.delete cannot delete a farm type', function () {
    $farmType = FarmType::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('farm-types.view');

    $this->actingAs($user)->delete("/admin/farm-types/{$farmType->id}")->assertForbidden();
});

test('a user with farm-types.delete can soft delete a farm type', function () {
    $farmType = FarmType::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo(['farm-types.view', 'farm-types.delete']);

    $this->actingAs($user)->delete("/admin/farm-types/{$farmType->id}")
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertSoftDeleted('farm_types', ['id' => $farmType->id]);
});

test('a soft-deleted farm type is excluded from the default index', function () {
    $active = FarmType::factory()->create(['name' => 'Cassava']);
    $deleted = FarmType::factory()->create(['name' => 'Yam']);
    $deleted->delete();

    $user = User::factory()->create();
    $user->givePermissionTo('farm-types.view');

    $response = $this->actingAs($user)->get('/admin/farm-types');

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->has('farmTypes.data', 1)
            ->where('farmTypes.data.0.name', 'Cassava')
    );
});
