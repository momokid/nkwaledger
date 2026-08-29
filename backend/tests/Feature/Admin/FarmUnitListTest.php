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

function allUnitPayload(array $overrides = []): array
{
    return array_merge([
        'farmer_uuid' => test()->farmer->uuid,
        'farm_type_id' => test()->farmType->id,
        'community_id' => test()->community->id,
        'name' => 'Pen A',
        'capacity' => 250,
        'capacity_unit' => 'birds',
    ], $overrides);
}

test('a guest is redirected to login', function () {
    $this->get('/agent/farm-units')->assertRedirect('/login');
});

test('a user without the view permission is forbidden', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/agent/farm-units')->assertForbidden();
});

test('an agent sees the page', function () {
    $this->actingAs($this->agent)->get('/agent/farm-units')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/FarmUnits/All')->has('units'));
});

test('an admin sees the page', function () {
    $this->actingAs($this->admin)->get('/admin/farm-units')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/FarmUnits/All'));
});

test('the page is told which frame to wear', function () {
    $this->actingAs($this->agent)->get('/agent/farm-units')
        ->assertInertia(fn($page) => $page->where('layout', 'agent')
            ->where('basePath', '/agent/farm-units'));
});

// an agent works their own book, so they see units across the farmers they hold
test('an agent sees units for every farmer they hold', function () {
    $second = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);
    FarmUnit::factory()->create(['farmer_profile_id' => $second->id]);

    $this->actingAs($this->agent)->get('/agent/farm-units')
        ->assertInertia(fn($page) => $page->has('units.data', 2));
});

test('an agent does not see units for farmers they do not hold', function () {
    $other = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);
    FarmUnit::factory()->create(['farmer_profile_id' => $other->id]);

    $this->actingAs($this->agent)->get('/agent/farm-units')
        ->assertInertia(fn($page) => $page->has('units.data', 1));
});

test('an admin sees every unit', function () {
    $other = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);
    FarmUnit::factory()->create(['farmer_profile_id' => $other->id]);

    $this->actingAs($this->admin)->get('/admin/farm-units')
        ->assertInertia(fn($page) => $page->has('units.data', 2));
});

test('each row names the farmer it belongs to', function () {
    FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);

    $this->actingAs($this->agent)->get('/agent/farm-units')
        ->assertInertia(fn($page) => $page->where('units.data.0.farmer_uuid', $this->farmer->uuid)
            ->has('units.data.0.farmer'));
});

// the picker only offers farmers this person can reach
test('an agent is offered only their own farmers', function () {
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    $this->actingAs($this->agent)->get('/agent/farm-units')
        ->assertInertia(fn($page) => $page->has('farmers', 1));
});

test('an admin is offered every farmer', function () {
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    $this->actingAs($this->admin)->get('/admin/farm-units')
        ->assertInertia(fn($page) => $page->has('farmers', 2));
});

test('a unit can be added from here', function () {
    $this->actingAs($this->agent)->post('/agent/farm-units', allUnitPayload())
        ->assertSessionDoesntHaveErrors();

    expect($this->farmer->fresh()->farmUnits)->toHaveCount(1);
});

test('adding records who did it', function () {
    $this->actingAs($this->agent)->post('/agent/farm-units', allUnitPayload());

    expect($this->farmer->fresh()->farmUnits->first()->created_by)->toBe($this->agent->id);
});

test('a farmer is required', function () {
    $this->actingAs($this->agent)->post('/agent/farm-units', allUnitPayload(['farmer_uuid' => null]))
        ->assertSessionHasErrors('farmer_uuid');
});

test('an unknown farmer is refused', function () {
    $this->actingAs($this->agent)->post('/agent/farm-units', allUnitPayload([
        'farmer_uuid' => '00000000-0000-0000-0000-000000000000',
    ]))->assertSessionHasErrors('farmer_uuid');
});

// an agent cannot add a unit onto someone else's farmer by posting their uuid
test('an agent cannot add a unit for a farmer they do not hold', function () {
    $other = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    $this->actingAs($this->agent)->post('/agent/farm-units', allUnitPayload([
        'farmer_uuid' => $other->uuid,
    ]))->assertNotFound();

    expect($other->fresh()->farmUnits)->toHaveCount(0);
});

test('a name is required', function () {
    $this->actingAs($this->agent)->post('/agent/farm-units', allUnitPayload(['name' => null]))
        ->assertSessionHasErrors('name');
});

test('two units on one farm cannot share a name', function () {
    FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id, 'name' => 'Pen A']);

    $this->actingAs($this->agent)->post('/agent/farm-units', allUnitPayload(['name' => 'Pen A']))
        ->assertSessionHasErrors('name');
});

test('a user without the create permission cannot add', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $vet->givePermissionTo('farm-units.view');

    $this->actingAs($vet)->post('/agent/farm-units', allUnitPayload())->assertForbidden();
});

test('the list can be filtered by farmer', function () {
    $second = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);
    FarmUnit::factory()->count(2)->create(['farmer_profile_id' => $second->id]);

    $this->actingAs($this->agent)->get("/agent/farm-units?farmer={$second->uuid}")
        ->assertInertia(fn($page) => $page->has('units.data', 2));
});

test('the filter is sent back to the page', function () {
    $this->actingAs($this->agent)->get("/agent/farm-units?farmer={$this->farmer->uuid}")
        ->assertInertia(fn($page) => $page->where('filters.farmer', $this->farmer->uuid));
});

test('the page says what this user may do', function () {
    $this->actingAs($this->agent)->get('/agent/farm-units')
        ->assertInertia(fn($page) => $page->where('permissions.create', true)
            ->where('permissions.approve', true));
});

test('the link appears in the agent nav', function () {
    $this->actingAs($this->agent)->get('/agent/farm-units')
        ->assertInertia(fn($page) => $page->where('auth.nav', fn($nav) => collect($nav)->contains('agent.farm-units.all')));
});
