<?php

use App\Enums\DiseaseReportStatus;
use App\Models\Community;
use App\Models\District;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('a guest is redirected to login', function () {
    $region = Region::create(['name' => 'Northern']);

    $this->get("/admin/regions/{$region->id}/health-detail")->assertRedirect('/login');
});

test('a non-admin is forbidden', function () {
    $region = Region::create(['name' => 'Northern']);
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $this->actingAs($farmer)
        ->get("/admin/regions/{$region->id}/health-detail")
        ->assertForbidden();
});

test('admin sees only disease reports for farmers in that region', function () {
    $region = Region::create(['name' => 'Ashanti']);
    $district = District::create(['name' => 'Kumasi', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Bantama', 'district_id' => $district->id]);

    $otherRegion = Region::create(['name' => 'Volta']);
    $otherDistrict = District::create(['name' => 'Ho', 'region_id' => $otherRegion->id]);
    $otherCommunity = Community::create(['name' => 'Bankoe', 'district_id' => $otherDistrict->id]);

    $farmerIn = FarmerProfile::factory()->create(['community_id' => $community->id]);
    $unitIn = FarmUnit::factory()->create(['farmer_profile_id' => $farmerIn->id]);

    $farmerOut = FarmerProfile::factory()->create(['community_id' => $otherCommunity->id]);
    $unitOut = FarmUnit::factory()->create(['farmer_profile_id' => $farmerOut->id]);

    $reportIn = DiseaseReport::factory()->create([
        'farm_unit_id' => $unitIn->id,
        'farmer_profile_id' => $farmerIn->id,
        'category' => 'Livestock',
        'status' => DiseaseReportStatus::New,
        'description' => 'A sick cow.',
    ]);
    DiseaseReport::factory()->create([
        'farm_unit_id' => $unitOut->id,
        'farmer_profile_id' => $farmerOut->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get("/admin/regions/{$region->id}/health-detail")
        ->assertOk()
        ->json();

    expect($response['reports'])->toHaveCount(1);
    expect($response['reports'][0]['uuid'])->toBe($reportIn->uuid);
    expect($response['reports'][0]['description'])->toBe('A sick cow.');
});
