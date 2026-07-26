<?php

use App\Models\District;
use App\Models\Region;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "farmer-groups.{$action}", 'guard_name' => 'web']);
    }
});

test('a guest is redirected to login when listing districts', function () {
    $this->get('/admin/districts')->assertRedirect('/login');
});

test('a user without farmer-groups.view cannot list districts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/districts')->assertForbidden();
});

test('listing districts filters by region_id', function () {
    $regionOne = Region::create(['name' => 'Northern']);
    $regionTwo = Region::create(['name' => 'Ashanti']);
    District::create(['name' => 'Tamale', 'region_id' => $regionOne->id]);
    District::create(['name' => 'Kumasi', 'region_id' => $regionTwo->id]);

    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $response = $this->actingAs($user)->get("/admin/districts?region_id={$regionOne->id}");

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonFragment(['name' => 'Tamale']);
});

test('a user without farmer-groups.create cannot create a district', function () {
    $region = Region::create(['name' => 'Northern']);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->post('/admin/districts', [
        'name' => 'Tamale',
        'region_id' => $region->id,
    ])->assertForbidden();
});

test('a user with farmer-groups.create can create a district', function () {
    $region = Region::create(['name' => 'Northern']);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/districts', [
        'name' => 'Tamale',
        'region_id' => $region->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('districts', ['name' => 'Tamale', 'region_id' => $region->id]);
});

test('creating a district with a non-existent region_id fails validation', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/districts', [
        'name' => 'Tamale',
        'region_id' => 999,
    ])->assertSessionHasErrors('region_id');
});

test('creating a district with a duplicate name in the same region fails validation', function () {
    $region = Region::create(['name' => 'Northern']);
    District::create(['name' => 'Central', 'region_id' => $region->id]);

    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/districts', [
        'name' => 'Central',
        'region_id' => $region->id,
    ])->assertSessionHasErrors('name');
});

test('the same district name is allowed in a different region', function () {
    $regionOne = Region::create(['name' => 'Northern']);
    $regionTwo = Region::create(['name' => 'Ashanti']);
    District::create(['name' => 'Central', 'region_id' => $regionOne->id]);

    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/districts', [
        'name' => 'Central',
        'region_id' => $regionTwo->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('districts', ['name' => 'Central', 'region_id' => $regionTwo->id]);
});

test('a user without farmer-groups.update cannot update a district', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->put("/admin/districts/{$district->id}", [
        'name' => 'Tamale Metro',
        'region_id' => $region->id,
    ])->assertForbidden();
});

test('a user with farmer-groups.update can update a district', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.update']);

    $this->actingAs($user)->put("/admin/districts/{$district->id}", [
        'name' => 'Tamale Metro',
        'region_id' => $region->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('districts', ['id' => $district->id, 'name' => 'Tamale Metro']);
});

test('a user without farmer-groups.delete cannot delete a district', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Yendi', 'region_id' => $region->id]);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->delete("/admin/districts/{$district->id}")->assertForbidden();
});

test('a user with farmer-groups.delete can soft delete a district', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Savelugu', 'region_id' => $region->id]);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.delete']);

    $this->actingAs($user)->delete("/admin/districts/{$district->id}")
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertSoftDeleted('districts', ['id' => $district->id]);
});
