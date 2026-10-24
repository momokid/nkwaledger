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

    $this->adviser = User::factory()->create();
    $this->adviser->assignRole('adviser');
    $this->vet = User::factory()->create();
    $this->vet->assignRole('vet');

    $user = User::factory()->create(['first_name' => 'Kofi', 'surname' => 'Mensah']);
    $user->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $user->id]);
    $this->unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id]);

    $this->report = fn(array $extra = []) => DiseaseReport::factory()->create($extra + [
        'farm_unit_id' => $this->unit->id,
        'farmer_profile_id' => $this->profile->id,
        'assigned_officer_id' => $this->adviser->id,
    ]);
});

it('stamps responded_at when the officer answers a report', function () {
    $report = ($this->report)();
    expect($report->responded_at)->toBeNull();

    $this->actingAs($this->adviser)->post("/adviser/reports/{$report->uuid}/respond", [
        'status' => 'resolved',
        'contact_method' => 'call',
        'note' => 'Spray the leaves.',
    ])->assertSessionDoesntHaveErrors();

    expect($report->fresh()->responded_at)->not->toBeNull();
});

it('lists only the reports this officer answered, newest answer first', function () {
    $older = ($this->report)(['status' => 'reviewed', 'contact_method' => 'call', 'response_note' => "First line\nsecond line", 'responded_at' => now()->subDays(3)]);
    $newer = ($this->report)(['status' => 'resolved', 'contact_method' => 'farm_visit', 'response_note' => 'Done.', 'responded_at' => now()->subDay()]);
    ($this->report)();
    ($this->report)(['assigned_officer_id' => $this->vet->id, 'status' => 'resolved', 'contact_method' => 'call', 'response_note' => 'x', 'responded_at' => now()]);

    $this->actingAs($this->adviser)
        ->get('/adviser/consultations')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Officer/Consultations')
            ->has('consultations', 2)
            ->where('consultations.0.uuid', $newer->uuid)
            ->where('consultations.0.contact_method', 'farm_visit')
            ->where('consultations.0.status', 'resolved')
            ->where('consultations.0.farmer_name', 'Mensah Kofi')
            ->where('consultations.0.responded_at', $newer->responded_at->toDateString())
            ->where('consultations.1.uuid', $older->uuid)
            ->where('consultations.1.note', 'First line')
            ->where('basePath', '/adviser'));
});

it('works for a vet at their own address and keeps the roles apart', function () {
    $this->actingAs($this->vet)->get('/vet/consultations')
        ->assertOk()
        ->assertInertia(fn($page) => $page->has('consultations', 0)->where('basePath', '/vet'));

    $this->actingAs($this->vet)->get('/adviser/consultations')->assertForbidden();
    $this->actingAs($this->adviser)->get('/vet/consultations')->assertForbidden();
});
