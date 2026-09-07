<?php

use App\Models\Community;
use App\Models\District;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "farmer-groups.{$action}", 'guard_name' => 'web']);
    }
});

test('a guest is redirected to login when listing communities', function () {
    $this->get('/admin/communities')->assertRedirect('/login');
});

test('a guest is redirected to login when requesting a location suggestion', function () {
    $this->get('/admin/communities/suggest-location?name=Kalpohin&district_id=1')
        ->assertRedirect('/login');
});

test('a user without farmer-groups.view cannot request a location suggestion', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get("/admin/communities/suggest-location?name=Kalpohin&district_id={$district->id}")
        ->assertForbidden();
});

test('a location suggestion returns coordinates found for the name and district', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 9.4008, 'longitude' => -0.8393]]]),
    ]);

    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $response = $this->actingAs($user)
        ->get("/admin/communities/suggest-location?name=Kalpohin&district_id={$district->id}")
        ->assertOk();

    $response->assertJson(['latitude' => 9.4008, 'longitude' => -0.8393]);
});

test('a location suggestion returns nulls gracefully when nothing is found', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => []]),
    ]);

    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $response = $this->actingAs($user)
        ->get("/admin/communities/suggest-location?name=Nowhereville&district_id={$district->id}")
        ->assertOk();

    $response->assertJson(['latitude' => null, 'longitude' => null]);
});

test('a location suggestion requires a name and a valid district', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)
        ->getJson('/admin/communities/suggest-location?district_id=999')
        ->assertJsonValidationErrors(['name', 'district_id']);
});

test('a user without farmer-groups.view cannot list communities', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/communities')->assertForbidden();
});

test('listing communities filters by district_id', function () {
    $region = Region::create(['name' => 'Northern']);
    $districtOne = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $districtTwo = District::create(['name' => 'Yendi', 'region_id' => $region->id]);
    Community::create(['name' => 'Kalpohin', 'district_id' => $districtOne->id]);
    Community::create(['name' => 'Zabzugu', 'district_id' => $districtTwo->id]);

    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $response = $this->actingAs($user)->get("/admin/communities?district_id={$districtOne->id}");

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonFragment(['name' => 'Kalpohin']);
});

test('a user without farmer-groups.create cannot create a community', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->post('/admin/communities', [
        'name' => 'Kalpohin',
        'district_id' => $district->id,
    ])->assertForbidden();
});

test('a user with farmer-groups.create can create a community', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/communities', [
        'name' => 'Kalpohin',
        'district_id' => $district->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('communities', ['name' => 'Kalpohin', 'district_id' => $district->id]);
});

test('creating a community with a non-existent district_id fails validation', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/communities', [
        'name' => 'Kalpohin',
        'district_id' => 999,
    ])->assertSessionHasErrors('district_id');
});

test('creating a community with a duplicate name in the same district fails validation', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    Community::create(['name' => 'Central', 'district_id' => $district->id]);

    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/communities', [
        'name' => 'Central',
        'district_id' => $district->id,
    ])->assertSessionHasErrors('name');
});

test('the same community name is allowed in a different district', function () {
    $region = Region::create(['name' => 'Northern']);
    $districtOne = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $districtTwo = District::create(['name' => 'Yendi', 'region_id' => $region->id]);
    Community::create(['name' => 'Central', 'district_id' => $districtOne->id]);

    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/communities', [
        'name' => 'Central',
        'district_id' => $districtTwo->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('communities', ['name' => 'Central', 'district_id' => $districtTwo->id]);
});

test('a user without farmer-groups.update cannot update a community', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Kalpohin', 'district_id' => $district->id]);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->put("/admin/communities/{$community->id}", [
        'name' => 'Kalpohin Estates',
        'district_id' => $district->id,
    ])->assertForbidden();
});

test('a user with farmer-groups.update can update a community', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Kalpohin', 'district_id' => $district->id]);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.update']);

    $this->actingAs($user)->put("/admin/communities/{$community->id}", [
        'name' => 'Kalpohin Estates',
        'district_id' => $district->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('communities', ['id' => $community->id, 'name' => 'Kalpohin Estates']);
});

test('a user without farmer-groups.delete cannot delete a community', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Diare', 'district_id' => $district->id]);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->delete("/admin/communities/{$community->id}")->assertForbidden();
});

test('a user with farmer-groups.delete can soft delete a community', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Vittin', 'district_id' => $district->id]);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.delete']);

    $this->actingAs($user)->delete("/admin/communities/{$community->id}")
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertSoftDeleted('communities', ['id' => $community->id]);
});
