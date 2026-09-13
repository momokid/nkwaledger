<?php

use App\Enums\OfficerRole;
use App\Models\AgentOfficerAssignment;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Storage::fake('public');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');

    $this->profile = FarmerProfile::factory()->create([
        'user_id' => $this->farmerUser->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $livestockCategory = FarmTypeCategory::create(['name' => 'Livestock']);
    $farmType = FarmType::factory()->withCategory($livestockCategory)->create();

    $this->unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $farmType->id,
    ]);
});

it('shows the report form to the farmer who owns the unit', function () {
    $this->actingAs($this->farmerUser)
        ->get("/my-farm/{$this->unit->id}/report-problem")
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('DiseaseReports/Create'));
});

it('turns away a farmer who does not own the unit', function () {
    $stranger = User::factory()->create();
    $stranger->assignRole('farmer');
    FarmerProfile::factory()->create(['user_id' => $stranger->id]);

    $this->actingAs($stranger)
        ->get("/my-farm/{$this->unit->id}/report-problem")
        ->assertNotFound();
});

it('requires a description and a photo', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-farm/{$this->unit->id}/report-problem", [])
        ->assertSessionHasErrors(['description', 'photo']);

    expect(DiseaseReport::count())->toBe(0);
});

it('refuses a file that is not an image', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-farm/{$this->unit->id}/report-problem", [
            'description' => 'Something is wrong',
            'photo' => UploadedFile::fake()->create('notes.pdf', 100),
        ])
        ->assertSessionHasErrors('photo');
});

it('records the report, routes it, and stores a compressed photo', function () {
    $photo = UploadedFile::fake()->image('sick-bird.jpg', 3000, 2000);
    $originalSize = filesize($photo->getRealPath());

    $this->actingAs($this->farmerUser)
        ->post("/my-farm/{$this->unit->id}/report-problem", [
            'description' => 'Some birds look weak and are not eating.',
            'photo' => $photo,
        ])
        ->assertRedirect(route('my-farm.index'))
        ->assertSessionHas('success');

    $report = DiseaseReport::first();

    expect($report)->not->toBeNull();
    expect($report->farm_unit_id)->toBe($this->unit->id);
    expect($report->farmer_profile_id)->toBe($this->profile->id);
    expect($report->reported_by)->toBeNull();
    expect($report->category)->toBe('Livestock');
    expect($report->routed_role)->toBe(OfficerRole::Vet);
    expect($report->description)->toBe('Some birds look weak and are not eating.');
    expect($report->status->value)->toBe('new');

    Storage::disk('public')->assertExists($report->photo_path);
    expect(Storage::disk('public')->size($report->photo_path))->toBeLessThan($originalSize);
});

it('leaves the report unassigned when the agent has no vet linked yet', function () {
    $this->actingAs($this->farmerUser)->post("/my-farm/{$this->unit->id}/report-problem", [
        'description' => 'Some birds look weak.',
        'photo' => UploadedFile::fake()->image('sick.jpg'),
    ]);

    expect(DiseaseReport::first()->assigned_officer_id)->toBeNull();
});

it('assigns the report to the vet already linked to the farmer\'s agent', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    AgentOfficerAssignment::create([
        'agent_id' => $this->agent->id,
        'officer_id' => $vet->id,
        'role' => OfficerRole::Vet,
    ]);

    $this->actingAs($this->farmerUser)->post("/my-farm/{$this->unit->id}/report-problem", [
        'description' => 'Some birds look weak.',
        'photo' => UploadedFile::fake()->image('sick.jpg'),
    ]);

    expect(DiseaseReport::first()->assigned_officer_id)->toBe($vet->id);
});

it('routes a crop farm unit to the adviser role', function () {
    $cropCategory = FarmTypeCategory::create(['name' => 'Crop']);
    $cropType = FarmType::factory()->withCategory($cropCategory)->create();
    $cropUnit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $cropType->id,
    ]);

    $this->actingAs($this->farmerUser)->post("/my-farm/{$cropUnit->id}/report-problem", [
        'description' => 'The leaves are turning yellow.',
        'photo' => UploadedFile::fake()->image('leaves.jpg'),
    ]);

    expect(DiseaseReport::first()->routed_role)->toBe(OfficerRole::Adviser);
});
