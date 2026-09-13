<?php

use App\Enums\OfficerRole;
use App\Models\AgentOfficerAssignment;
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

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->vet = User::factory()->create();
    $this->vet->assignRole('vet');
});

it('turns a guest away', function () {
    $this->get('/admin/officer-assignments')->assertRedirect('/login');
});

it('turns away a user without officer-assignments.view', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $this->actingAs($farmer)->get('/admin/officer-assignments')->assertForbidden();
});

it('shows the admin the page with agents, vets and advisers to choose from', function () {
    $this->actingAs($this->admin)
        ->get('/admin/officer-assignments')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Admin/OfficerAssignments/Index')
            ->has('agents', 1)
            ->has('vets', 1));
});

it('links an agent to an officer', function () {
    $this->actingAs($this->admin)
        ->post('/admin/officer-assignments', [
            'agent_id' => $this->agent->id,
            'officer_id' => $this->vet->id,
            'role' => 'vet',
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertSessionHas('success');

    expect(AgentOfficerAssignment::where('agent_id', $this->agent->id)
        ->where('officer_id', $this->vet->id)
        ->where('role', OfficerRole::Vet)
        ->exists())->toBeTrue();
});

it('auto-assigns any waiting reports for that agent\'s farmers the moment the link is created', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $profile->id]);

    $waiting = DiseaseReport::factory()->create([
        'farm_unit_id' => $unit->id,
        'farmer_profile_id' => $profile->id,
        'routed_role' => OfficerRole::Vet,
        'assigned_officer_id' => null,
    ]);

    $this->actingAs($this->admin)->post('/admin/officer-assignments', [
        'agent_id' => $this->agent->id,
        'officer_id' => $this->vet->id,
        'role' => 'vet',
    ]);

    expect($waiting->fresh()->assigned_officer_id)->toBe($this->vet->id);
});

it('refuses an agent_id that does not belong to an agent', function () {
    $notAnAgent = User::factory()->create();
    $notAnAgent->assignRole('farmer');

    $this->actingAs($this->admin)
        ->post('/admin/officer-assignments', [
            'agent_id' => $notAnAgent->id,
            'officer_id' => $this->vet->id,
            'role' => 'vet',
        ])
        ->assertSessionHasErrors('agent_id');
});

it('refuses an officer_id whose role does not match', function () {
    $adviser = User::factory()->create();
    $adviser->assignRole('adviser');

    $this->actingAs($this->admin)
        ->post('/admin/officer-assignments', [
            'agent_id' => $this->agent->id,
            'officer_id' => $adviser->id,
            'role' => 'vet',
        ])
        ->assertSessionHasErrors('officer_id');
});

it('refuses linking the same agent, officer and role twice', function () {
    AgentOfficerAssignment::create([
        'agent_id' => $this->agent->id,
        'officer_id' => $this->vet->id,
        'role' => OfficerRole::Vet,
    ]);

    $this->actingAs($this->admin)
        ->post('/admin/officer-assignments', [
            'agent_id' => $this->agent->id,
            'officer_id' => $this->vet->id,
            'role' => 'vet',
        ])
        ->assertSessionHasErrors('officer_id');
});

it('removes an assignment', function () {
    $assignment = AgentOfficerAssignment::create([
        'agent_id' => $this->agent->id,
        'officer_id' => $this->vet->id,
        'role' => OfficerRole::Vet,
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/officer-assignments/{$assignment->id}")
        ->assertSessionHas('success');

    expect(AgentOfficerAssignment::find($assignment->id))->toBeNull();
});

it('turns away a user without officer-assignments.delete', function () {
    $assignment = AgentOfficerAssignment::create([
        'agent_id' => $this->agent->id,
        'officer_id' => $this->vet->id,
        'role' => OfficerRole::Vet,
    ]);

    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $this->actingAs($farmer)
        ->delete("/admin/officer-assignments/{$assignment->id}")
        ->assertForbidden();

    expect(AgentOfficerAssignment::find($assignment->id))->not->toBeNull();
});
