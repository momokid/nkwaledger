<?php

use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $this->stranger = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    // added by somebody else, so this agent may approve it
    $this->unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->farmer->id,
        'created_by' => $this->otherAgent->id,
    ]);
});

it('shows the queue to someone who may approve', function () {
    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Approvals/Index'));
});

it('turns away someone without the permission', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/agent/approvals')->assertForbidden();
});

it('lists a farm unit waiting to be checked', function () {
    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.kind', 'farm_unit')
            ->where('items.data.0.id', $this->unit->id));
});

it('leaves out a unit already checked', function () {
    $this->unit->forceFill(['approved_at' => now(), 'approved_by' => $this->admin->id])->save();

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->has('items.data', 0));
});

// an agent should see their own work sitting unapproved, they just cannot sign it off
it('shows a unit this person added themselves, without a button', function () {
    $mine = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->farmer->id,
        'created_by' => $this->agent->id,
    ]);

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->where('items.data', function ($items) use ($mine) {
            $row = collect($items)->firstWhere('id', $mine->id);

            return $row !== null && $row['can_approve'] === false;
        }));
});

it('marks a row this person may sign off', function () {
    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->where('items.data.0.can_approve', true));
});

// a new stock writes its own opening movement, so both wait to be checked
it('lists a stock count waiting to be checked', function () {
    FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page
            ->has('items.data', 3)
            ->where('items.data', function ($items) {
                return collect($items)->pluck('kind')->contains('stock');
            }));
});

it('shows a stock count this person recorded, without a button', function () {
    FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->agent->id,
    ]);

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->where('items.data', function ($items) {
            return collect($items)->where('kind', 'stock')->every(fn($row) => $row['can_approve'] === false);
        }));
});

it('lists a stock movement waiting to be checked', function () {
    $stock = FarmUnitStock::factory()->confirmed()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
        'confirmed_by' => $this->admin->id,
    ]);

    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $kinds = collect();

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->where('items.data', function ($items) {
            return collect($items)->pluck('kind')->contains('stock_movement');
        }));
});

// an agent works their own book and nobody else's
it('reads only the farmers this agent holds', function () {
    FarmUnit::factory()->create([
        'farmer_profile_id' => $this->stranger->id,
        'created_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->has('items.data', 1));
});

it('shows an admin every farmer', function () {
    FarmUnit::factory()->create([
        'farmer_profile_id' => $this->stranger->id,
        'created_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/approvals')
        ->assertInertia(fn($page) => $page->has('items.data', 2));
});

// the thing waiting longest needs you most
it('puts the oldest thing first', function () {
    $older = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->farmer->id,
        'created_by' => $this->otherAgent->id,
    ]);

    $older->forceFill(['created_at' => now()->subWeeks(3)])->saveQuietly();

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->where('items.data.0.id', $older->id));
});

it('says whose farm each thing belongs to', function () {
    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->where(
            'items.data.0.farmer',
            "{$this->farmer->user?->surname} {$this->farmer->user?->first_name}",
        ));
});

it('says who added it and how long it has waited', function () {
    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page
            ->where('items.data.0.added_by', $this->otherAgent->surname)
            ->has('items.data.0.waiting_since'));
});

// the row opens in place, so the detail travels with it
it('carries the detail each row needs when it opens', function () {
    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->has('items.data.0.details'));
});

// a unit with records piling up on it is the urgent one
it('counts what is already waiting on an unchecked unit', function () {
    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->has('items.data.0.details.provisional_records'));
});

it('breaks a long queue into pages', function () {
    FarmUnit::factory()->count(30)->create([
        'farmer_profile_id' => $this->farmer->id,
        'created_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->has('items.data', 25));
});

it('comes back empty when nothing is waiting', function () {
    $this->unit->forceFill(['approved_at' => now(), 'approved_by' => $this->admin->id])->save();

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->has('items.data', 0));
});

// the badge counts only what this person can act on
it('sends a count of what is waiting', function () {
    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->where('auth.pendingApprovals', 1));
});

// the badge counts what you can act on, not what is on the page
it('counts nothing for somebody who can approve nothing', function () {
    FarmUnit::factory()->create([
        'farmer_profile_id' => $this->farmer->id,
        'created_by' => $this->agent->id,
    ]);

    $this->unit->forceFill(['approved_at' => now(), 'approved_by' => $this->admin->id])->save();

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page
            ->where('auth.pendingApprovals', 0)
            ->has('items.data', 1));
});
