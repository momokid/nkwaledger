<?php

use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

// one self-contained fake per test, since a later Http::fake() call does not
// reliably override an earlier one for the same URL pattern within a test
function fakeSevenDayWeather(): void
{
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 6.7, 'longitude' => -1.5]]]),
        'api.open-meteo.com/*' => Http::response(['daily' => [
            'time' => ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
            'precipitation_sum' => [2, 25, 3, 1, 0, 5, 2],
            'temperature_2m_max' => [28, 29, 27, 36, 30, 31, 29],
            'windspeed_10m_max' => [15, 18, 12, 20, 45, 22, 14],
        ]]),
    ]);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    $this->cropCategory = FarmTypeCategory::create(['name' => 'Crop']);
    $this->livestockCategory = FarmTypeCategory::create(['name' => 'Livestock']);
});

test('a guest is redirected to login', function () {
    $this->get('/my-weather')->assertRedirect('/login');
});

test('a farmer with no profile is forbidden', function () {
    $bare = User::factory()->create();
    $bare->assignRole('farmer');

    $this->actingAs($bare)->get('/my-weather')->assertForbidden();
});

test('a user without the farm-units permission is forbidden', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/my-weather')->assertForbidden();
});

test('a farmer with no farm units sees an empty page, not an error', function () {
    $this->actingAs($this->farmerUser)->get('/my-weather')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('MyWeather/Index')->has('locations', 0));
});

test('a farmer sees a 7-day forecast plus todays advice for their farm unit', function () {
    fakeSevenDayWeather();

    $community = Community::factory()->create();
    $type = FarmType::create(['name' => 'Maize', 'category_id' => $this->cropCategory->id]);

    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
        'community_id' => $community->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-weather')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->has('locations', 1)
            ->where('locations.0.community', $community->name)
            ->where('locations.0.available', true)
            ->has('locations.0.forecast', 7)
            ->where('locations.0.forecast.1.condition', 'heavy_rain')
            ->has('locations.0.advice'));
});

test('two farm units in the same community are reported once, not twice', function () {
    fakeSevenDayWeather();

    $community = Community::factory()->create();
    $type = FarmType::create(['name' => 'Maize', 'category_id' => $this->cropCategory->id]);

    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
        'community_id' => $community->id,
    ]);
    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
        'community_id' => $community->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-weather')
        ->assertInertia(fn($page) => $page->has('locations', 1));
});

test('farm units in different communities are each reported separately', function () {
    fakeSevenDayWeather();

    $communityA = Community::factory()->create();
    $communityB = Community::factory()->create();
    $type = FarmType::create(['name' => 'Goats', 'category_id' => $this->livestockCategory->id]);

    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
        'community_id' => $communityA->id,
    ]);
    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
        'community_id' => $communityB->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-weather')
        ->assertInertia(fn($page) => $page->has('locations', 2));
});

test('a location is marked unavailable when it cannot be resolved', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => []]),
        'api.open-meteo.com/*' => Http::response(['daily' => []]),
    ]);

    $community = Community::factory()->create(['latitude' => null, 'longitude' => null]);
    $type = FarmType::create(['name' => 'Goats', 'category_id' => $this->livestockCategory->id]);

    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
        'community_id' => $community->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-weather')
        ->assertInertia(fn($page) => $page->where('locations.0.available', false));
});

test('type-specific alerts appear for farm types that have one', function () {
    fakeSevenDayWeather();

    $community = Community::factory()->create();
    $type = FarmType::create(['name' => 'Maize', 'category_id' => $this->cropCategory->id]);

    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
        'community_id' => $community->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-weather')
        ->assertInertia(fn($page) => $page
            ->has('locations.0.alerts', 1)
            ->where('locations.0.alerts.0.farm_type', 'Maize'));
});

test('farm types without a specific alert do not add one', function () {
    fakeSevenDayWeather();

    $community = Community::factory()->create();
    $type = FarmType::create(['name' => 'Yam', 'category_id' => $this->cropCategory->id]);

    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
        'community_id' => $community->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-weather')
        ->assertInertia(fn($page) => $page->has('locations.0.alerts', 0));
});
test('a normal day shows the actual weather code description instead of a generic message', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 6.7, 'longitude' => -1.5]]]),
        'api.open-meteo.com/*' => Http::response(['daily' => [
            'time' => ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
            'precipitation_sum' => [2, 2, 2, 2, 2, 2, 2],
            'temperature_2m_max' => [29, 29, 29, 29, 29, 29, 29],
            'windspeed_10m_max' => [15, 15, 15, 15, 15, 15, 15],
            'weathercode' => [61, 61, 61, 61, 61, 61, 61],
        ]]),
    ]);

    $community = Community::factory()->create();
    $type = FarmType::create(['name' => 'Yam', 'category_id' => $this->cropCategory->id]);

    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
        'community_id' => $community->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-weather')
        ->assertInertia(fn($page) => $page
            ->where('locations.0.headline', 'Slight rain, around 29°C.'));
});

test('each location includes a weekly outlook summary', function () {
    fakeSevenDayWeather();

    $community = Community::factory()->create();
    $type = FarmType::create(['name' => 'Yam', 'category_id' => $this->cropCategory->id]);

    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
        'community_id' => $community->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-weather')
        ->assertInertia(fn($page) => $page->has('locations.0.outlook'));
});
