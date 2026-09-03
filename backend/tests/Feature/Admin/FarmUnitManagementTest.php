<?php

use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
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

    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    $this->farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $this->community = Community::factory()->create();
    $this->farmType = FarmType::factory()->withCategory()->create();
});

function unitPayload(array $overrides = []): array
{
    return array_merge([
        'farm_type_id' => test()->farmType->id,
        'community_id' => test()->community->id,
        'name' => 'Pen A',
        'capacity' => 250,
        'capacity_unit' => 'birds',
    ], $overrides);
}

test('a guest is redirected to login', function () {
    $this->get("/admin/farmers/{$this->farmer->uuid}/units")->assertRedirect('/login');
});

test('a user without the view permission is forbidden', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get("/admin/farmers/{$this->farmer->uuid}/units")->assertForbidden();
});

test('an admin sees the list page', function () {
    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units")
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/FarmUnits/Index')->has('units'));
});

test('the page carries the farmer it belongs to', function () {
    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units")
        ->assertInertia(fn($page) => $page->where('farmer.id', $this->farmer->uuid));
});

test('an agent sees units for a farmer they hold', function () {
    FarmUnit::factory()->count(2)->create(['farmer_profile_id' => $this->farmer->id]);

    $this->actingAs($this->agent)->get("/agent/farmers/{$this->farmer->uuid}/units")
        ->assertOk()
        ->assertInertia(fn($page) => $page->has('units', 2));
});

// a farmer belongs to one agent, so their pens do too
test('an agent cannot see units for a farmer they do not hold', function () {
    $other = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    $this->actingAs($this->agent)->get("/agent/farmers/{$other->uuid}/units")->assertNotFound();
});

test('the page is told which frame to wear', function () {
    $this->actingAs($this->agent)->get("/agent/farmers/{$this->farmer->uuid}/units")
        ->assertInertia(fn($page) => $page->where('layout', 'agent'));
});

test('a unit can be added', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload())
        ->assertSessionDoesntHaveErrors();

    expect($this->farmer->fresh()->farmUnits)->toHaveCount(1);
});

test('adding a unit records who did it', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload());

    expect($this->farmer->fresh()->farmUnits->first()->created_by)->toBe($this->agent->id);
});

test('a new unit is not approved', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload());

    expect($this->farmer->fresh()->farmUnits->first()->isApproved())->toBeFalse();
});

// weather follows the land, so the unit can sit somewhere else
test('a unit can sit in a different community from the farmer', function () {
    $elsewhere = Community::factory()->create();

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload([
        'community_id' => $elsewhere->id,
    ]));

    expect($this->farmer->fresh()->farmUnits->first()->community_id)->toBe($elsewhere->id);
});

test('a name is required', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload(['name' => null]))
        ->assertSessionHasErrors('name');
});

test('a farm type is required', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload(['farm_type_id' => null]))
        ->assertSessionHasErrors('farm_type_id');
});

test('a community is required', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload(['community_id' => null]))
        ->assertSessionHasErrors('community_id');
});

test('two units on one farm cannot share a name', function () {
    FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id, 'name' => 'Pen A']);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload(['name' => 'Pen A']))
        ->assertSessionHasErrors('name');
});

test('a capacity below zero is refused', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload(['capacity' => -5]))
        ->assertSessionHasErrors('capacity');
});

test('a unit with no capacity is allowed', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload(['capacity' => null]))
        ->assertSessionDoesntHaveErrors();
});

test('a user without the create permission cannot add a unit', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $vet->givePermissionTo('farm-units.view');

    $this->actingAs($vet)->post("/admin/farmers/{$this->farmer->uuid}/units", unitPayload())->assertForbidden();
});

test('a unit can be edited', function () {
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);

    $this->actingAs($this->agent)->put("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}", unitPayload([
        'name' => 'Pen B',
        'is_active' => true,
    ]))->assertSessionDoesntHaveErrors();

    expect($unit->fresh()->name)->toBe('Pen B');
});

test('a unit can be put on hold', function () {
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);

    $this->actingAs($this->agent)->put("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}", unitPayload([
        'is_active' => false,
    ]));

    expect($unit->fresh()->is_active)->toBeFalse();
});

test('a unit cannot be deleted', function () {
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);

    $this->actingAs($this->admin)->delete("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}")
        ->assertMethodNotAllowed();
});

test('a unit belonging to another farmer cannot be edited here', function () {
    $other = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $other->id]);

    $this->actingAs($this->agent)->put("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}", unitPayload())
        ->assertNotFound();
});

test('a unit can be approved', function () {
    $unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->farmer->id,
        'created_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}/approve")
        ->assertSessionDoesntHaveErrors();

    expect($unit->fresh()->isApproved())->toBeTrue();
});

test('approving records who did it', function () {
    $unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->farmer->id,
        'created_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}/approve");

    expect($unit->fresh()->approved_by)->toBe($this->agent->id);
});

// whoever set the pen up is not the one who says it exists
test('the person who added a unit cannot approve it', function () {
    $unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->farmer->id,
        'created_by' => $this->agent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}/approve")
        ->assertSessionHas('error');

    expect($unit->fresh()->isApproved())->toBeFalse();
});

test('a user without the approve permission cannot approve', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $vet->givePermissionTo('farm-units.view');

    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);

    $this->actingAs($vet)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}/approve")
        ->assertForbidden();
});

test('an already approved unit cannot be approved again', function () {
    $unit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->farmer->id,
        'created_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}/approve")
        ->assertSessionHas('error');
});

test('approving is written to the audit log', function () {
    $unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->farmer->id,
        'created_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}/approve");

    expect(App\Models\AuditLog::where('action', 'farm_unit.approved')->exists())->toBeTrue();
});

test('the page says what this user may do', function () {
    $this->actingAs($this->agent)->get("/admin/farmers/{$this->farmer->uuid}/units")
        ->assertInertia(fn($page) => $page->where('permissions.create', true)
            ->where('permissions.approve', true));
});
