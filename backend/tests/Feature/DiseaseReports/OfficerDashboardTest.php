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

    $this->vet = User::factory()->create();
    $this->vet->assignRole('vet');

    $this->otherVet = User::factory()->create();
    $this->otherVet->assignRole('vet');

    $this->farmer = User::factory()->create();
    $this->farmer->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmer->id]);

    $this->unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id]);
});

describe('dashboard', function () {
    it('shows a vet only the reports assigned to them', function () {
        $mine = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'routed_role' => OfficerRole::Vet,
            'assigned_officer_id' => $this->vet->id,
        ]);
        $notMine = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'routed_role' => OfficerRole::Vet,
            'assigned_officer_id' => $this->otherVet->id,
        ]);

        $this->actingAs($this->vet)
            ->get('/vet/dashboard')
            ->assertOk()
            ->assertInertia(fn($page) => $page
                ->component('Vet/Dashboard')
                ->has('reports', 1)
                ->where('reports.0.uuid', $mine->uuid));
    });

    it('turns an unassigned role away from the vet dashboard', function () {
        $adviser = User::factory()->create();
        $adviser->assignRole('adviser');

        $this->actingAs($adviser)->get('/vet/dashboard')->assertForbidden();
    });
});

describe('show', function () {
    it('shows the assigned officer their report, including the photo and description', function () {
        $report = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'assigned_officer_id' => $this->vet->id,
            'description' => 'Some birds are weak.',
        ]);

        $this->actingAs($this->vet)
            ->get("/vet/reports/{$report->uuid}")
            ->assertOk()
            ->assertInertia(fn($page) => $page
                ->component('Officer/ReportShow')
                ->where('report.description', 'Some birds are weak.')
                ->where('report.uuid', $report->uuid));
    });

    it('turns away an officer the report is not assigned to', function () {
        $report = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'assigned_officer_id' => $this->otherVet->id,
        ]);

        $this->actingAs($this->vet)
            ->get("/vet/reports/{$report->uuid}")
            ->assertNotFound();
    });

    it('sends the farm unit\'s other reports as history, newest first, excluding itself', function () {
        $older = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'assigned_officer_id' => $this->vet->id,
            'description' => 'Earlier issue',
            'created_at' => now()->subDays(3),
        ]);
        $current = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'assigned_officer_id' => $this->vet->id,
            'description' => 'Current issue',
        ]);

        $this->actingAs($this->vet)
            ->get("/vet/reports/{$current->uuid}")
            ->assertInertia(fn($page) => $page
                ->has('history', 1)
                ->where('history.0.description', 'Earlier issue'));
    });
});

describe('respond', function () {
    it('lets the assigned vet set status, contact method and a note', function () {
        $report = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'assigned_officer_id' => $this->vet->id,
        ]);

        $this->actingAs($this->vet)
            ->post("/vet/reports/{$report->uuid}/respond", [
                'status' => 'resolved',
                'contact_method' => 'farm_visit',
                'note' => 'Treated the flock, recovering well.',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertSessionHas('success');

        $fresh = $report->fresh();
        expect($fresh->status->value)->toBe('resolved');
        expect($fresh->contact_method->value)->toBe('farm_visit');
        expect($fresh->response_note)->toBe('Treated the flock, recovering well.');
    });

    it('refuses a response from an officer the report is not assigned to', function () {
        $report = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'assigned_officer_id' => $this->otherVet->id,
        ]);

        $this->actingAs($this->vet)
            ->post("/vet/reports/{$report->uuid}/respond", [
                'status' => 'resolved',
                'contact_method' => 'call',
                'note' => 'Should not be allowed.',
            ])
            ->assertNotFound();

        expect($report->fresh()->status->value)->toBe('new');
    });

    it('requires a contact method and a note', function () {
        $report = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'assigned_officer_id' => $this->vet->id,
        ]);

        $this->actingAs($this->vet)
            ->post("/vet/reports/{$report->uuid}/respond", ['status' => 'resolved'])
            ->assertSessionHasErrors(['contact_method', 'note']);
    });

    it('refuses "new" as a response status', function () {
        $report = DiseaseReport::factory()->create([
            'farm_unit_id' => $this->unit->id,
            'farmer_profile_id' => $this->profile->id,
            'assigned_officer_id' => $this->vet->id,
        ]);

        $this->actingAs($this->vet)
            ->post("/vet/reports/{$report->uuid}/respond", [
                'status' => 'new',
                'contact_method' => 'call',
                'note' => 'Note',
            ])
            ->assertSessionHasErrors('status');
    });
});
