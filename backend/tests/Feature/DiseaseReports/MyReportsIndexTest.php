<?php

use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
    $this->unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id]);
});

it('shows a guest to login', function () {
    $this->get('/my-farm/reports')->assertRedirect('/login');
});

it('lists only the farmer\'s own reports, most recent first', function () {
    $older = DiseaseReport::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'farmer_profile_id' => $this->profile->id,
        'category' => 'Livestock',
        'created_at' => now()->subDay(),
    ]);
    $newer = DiseaseReport::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'farmer_profile_id' => $this->profile->id,
        'category' => 'Crop',
    ]);

    $otherFarmer = FarmerProfile::factory()->create();
    $otherUnit = FarmUnit::factory()->create(['farmer_profile_id' => $otherFarmer->id]);
    DiseaseReport::factory()->create([
        'farm_unit_id' => $otherUnit->id,
        'farmer_profile_id' => $otherFarmer->id,
    ]);

    $this->actingAs($this->farmerUser)
        ->get('/my-farm/reports')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('DiseaseReports/Index')
            ->has('reports', 2)
            ->where('reports.0.uuid', $newer->uuid)
            ->where('reports.1.uuid', $older->uuid));
});
