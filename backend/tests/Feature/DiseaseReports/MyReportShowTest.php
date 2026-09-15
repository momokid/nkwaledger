<?php

use App\Enums\ContactMethod;
use App\Enums\DiseaseReportStatus;
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
    $report = DiseaseReport::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'farmer_profile_id' => $this->profile->id,
    ]);

    $this->get("/my-farm/reports/{$report->uuid}")->assertRedirect('/login');
});

it('shows the farmer their own report, waiting for review when there is no response yet', function () {
    $report = DiseaseReport::factory()->withAudio()->create([
        'farm_unit_id' => $this->unit->id,
        'farmer_profile_id' => $this->profile->id,
        'category' => 'Livestock',
        'description' => 'Some birds are weak.',
    ]);

    $this->actingAs($this->farmerUser)
        ->get("/my-farm/reports/{$report->uuid}")
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('DiseaseReports/Show')
            ->where('report.uuid', $report->uuid)
            ->where('report.description', 'Some birds are weak.')
            ->where('report.category', 'Livestock')
            ->where('report.status', 'new')
            ->where('report.farm_unit_name', $this->unit->name)
            ->where('report.contact_method', null)
            ->where('report.response_note', null)
            ->where('report.photo_url', fn($url) => str_ends_with($url, $report->photo_path))
            ->where('report.audio_url', fn($url) => str_ends_with($url, $report->audio_path)));
});

it('shows the officer\'s response once one exists', function () {
    $report = DiseaseReport::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'farmer_profile_id' => $this->profile->id,
    ]);
    $report->respond(DiseaseReportStatus::Resolved, ContactMethod::FarmVisit, 'Treated on site.');

    $this->actingAs($this->farmerUser)
        ->get("/my-farm/reports/{$report->uuid}")
        ->assertInertia(fn($page) => $page
            ->where('report.status', 'resolved')
            ->where('report.contact_method', 'farm_visit')
            ->where('report.response_note', 'Treated on site.'));
});

it('refuses a report belonging to another farmer', function () {
    $otherFarmer = FarmerProfile::factory()->create();
    $otherUnit = FarmUnit::factory()->create(['farmer_profile_id' => $otherFarmer->id]);
    $report = DiseaseReport::factory()->create([
        'farm_unit_id' => $otherUnit->id,
        'farmer_profile_id' => $otherFarmer->id,
    ]);

    $this->actingAs($this->farmerUser)
        ->get("/my-farm/reports/{$report->uuid}")
        ->assertForbidden();
});
