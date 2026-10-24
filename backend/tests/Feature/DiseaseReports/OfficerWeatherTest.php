<?php

use App\Models\Community;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 6.7, 'longitude' => -1.5]]]),
        'api.open-meteo.com/*' => Http::response(['daily' => [
            'time' => ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
            'precipitation_sum' => [2, 25, 3, 1, 0, 5, 2],
            'temperature_2m_max' => [28, 29, 27, 36, 30, 31, 29],
            'windspeed_10m_max' => [15, 18, 12, 20, 45, 22, 14],
        ]]),
    ]);

    $this->adviser = User::factory()->create();
    $this->adviser->assignRole('adviser');
    $this->vet = User::factory()->create();
    $this->vet->assignRole('vet');

    $category = FarmTypeCategory::create(['name' => 'Crop']);
    $type = FarmType::create(['name' => 'Maize', 'category_id' => $category->id]);
    $this->community = Community::factory()->create();

    $this->makeFarmer = function (?User $officer) use ($type) {
        $user = User::factory()->create(['first_name' => 'Kofi', 'surname' => 'Mensah']);
        $user->assignRole('farmer');
        $profile = FarmerProfile::factory()->create(['user_id' => $user->id]);
        $unit = FarmUnit::factory()->approved()->create([
            'farmer_profile_id' => $profile->id,
            'farm_type_id' => $type->id,
            'community_id' => $this->community->id,
        ]);
        if ($officer) {
            DiseaseReport::factory()->create([
                'farm_unit_id' => $unit->id,
                'farmer_profile_id' => $profile->id,
                'assigned_officer_id' => $officer->id,
            ]);
        }

        return $profile;
    };
});

it('lists only the farmers who sent the adviser a report', function () {
    $mine = ($this->makeFarmer)($this->adviser);
    ($this->makeFarmer)(null);
    ($this->makeFarmer)($this->vet);

    $this->actingAs($this->adviser)
        ->get('/adviser/weather')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Officer/WeatherFarmers')
            ->has('farmers', 1)
            ->where('farmers.0.uuid', $mine->uuid)
            ->where('farmers.0.name', 'Mensah Kofi')
            ->where('farmers.0.community', $this->community->name)
            ->where('basePath', '/adviser'));
});

it('shows a farmer\'s weather to an officer who got their report', function () {
    $farmer = ($this->makeFarmer)($this->adviser);

    $this->actingAs($this->adviser)
        ->get("/adviser/weather/{$farmer->uuid}")
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('MyWeather/Index')
            ->has('locations', 1)
            ->where('locations.0.community', $this->community->name)
            ->where('farmerName', 'Mensah Kofi')
            ->where('backHref', '/adviser/weather'));
});

it('turns away an officer who never got a report from that farmer', function () {
    $farmer = ($this->makeFarmer)($this->vet);

    $this->actingAs($this->adviser)->get("/adviser/weather/{$farmer->uuid}")->assertNotFound();
});

it('works the same for a vet at their own address', function () {
    $farmer = ($this->makeFarmer)($this->vet);

    $this->actingAs($this->vet)->get('/vet/weather')
        ->assertInertia(fn($page) => $page->has('farmers', 1)->where('basePath', '/vet'));
    $this->actingAs($this->vet)->get("/vet/weather/{$farmer->uuid}")
        ->assertInertia(fn($page) => $page->where('backHref', '/vet/weather'));
});

it('keeps the roles apart', function () {
    $this->actingAs($this->vet)->get('/adviser/weather')->assertForbidden();
    $this->actingAs($this->adviser)->get('/vet/weather')->assertForbidden();
});
