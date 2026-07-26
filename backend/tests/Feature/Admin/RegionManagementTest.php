<?php

use App\Models\Region;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "farmer-groups.{$action}", 'guard_name' => 'web']);
    }
});

test('a guest is redirected to login when listing regions', function () {
    $this->get('/admin/regions')->assertRedirect('/login');
});

test('a user without farmer-groups.view cannot list regions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/regions')->assertForbidden();
});

test('a user with farmer-groups.view can list regions', function () {
    Region::create(['name' => 'Northern']);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->get('/admin/regions')->assertOk();
});

test('a user without farmer-groups.create cannot create a region', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->post('/admin/regions', ['name' => 'Ashanti'])->assertForbidden();
});

test('a user with farmer-groups.create can create a region', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/regions', ['name' => 'Ashanti'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('regions', ['name' => 'Ashanti']);
});

test('creating a region with a duplicate name fails validation', function () {
    Region::create(['name' => 'Volta']);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/regions', ['name' => 'Volta'])
        ->assertSessionHasErrors('name');
});

test('a user without farmer-groups.update cannot update a region', function () {
    $region = Region::create(['name' => 'Bono']);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->put("/admin/regions/{$region->id}", ['name' => 'Bono East'])
        ->assertForbidden();
});

test('a user with farmer-groups.update can update a region', function () {
    $region = Region::create(['name' => 'Bono']);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.update']);

    $this->actingAs($user)->put("/admin/regions/{$region->id}", ['name' => 'Bono East'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('regions', ['id' => $region->id, 'name' => 'Bono East']);
});

test('a user without farmer-groups.delete cannot delete a region', function () {
    $region = Region::create(['name' => 'Western']);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->delete("/admin/regions/{$region->id}")->assertForbidden();
});

test('a user with farmer-groups.delete can soft delete a region', function () {
    $region = Region::create(['name' => 'Eastern']);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.delete']);

    $this->actingAs($user)->delete("/admin/regions/{$region->id}")
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertSoftDeleted('regions', ['id' => $region->id]);
});
