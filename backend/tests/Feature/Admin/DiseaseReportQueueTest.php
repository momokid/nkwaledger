<?php

use App\Enums\OfficerRole;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->profile = FarmerProfile::factory()->create();
    $this->unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id]);
});

it('turns a guest away', function () {
    $this->get('/admin/disease-reports')->assertRedirect('/login');
});

it('turns away a user without disease-reports.view', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $this->actingAs($farmer)->get('/admin/disease-reports')->assertForbidden();
});

it('lists only reports with no officer assigned yet', function () {
    $waiting = DiseaseReport::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'farmer_profile_id' => $this->profile->id,
        'assigned_officer_id' => null,
    ]);
    $assigned = DiseaseReport::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'farmer_profile_id' => $this->profile->id,
        'assigned_officer_id' => User::factory()->create()->id,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/disease-reports')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Admin/DiseaseReports/Index')
            ->has('reports', 1)
            ->where('reports.0.uuid', $waiting->uuid));

    expect($assigned->assigned_officer_id)->not->toBeNull();
});
