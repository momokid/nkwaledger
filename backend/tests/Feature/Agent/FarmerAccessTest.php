<?php

use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->community = Community::factory()->create();
    $this->farmType = FarmType::factory()->withCategory()->create();
});

test('a guest is redirected to login', function () {
    $this->get('/agent/farmers')->assertRedirect('/login');
});

test('an agent sees the list page', function () {
    $this->actingAs($this->agent)->get('/agent/farmers')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/Farmers/Index'));
});

// the frame differs, the permission does not
test('a user without the view permission is refused', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/agent/farmers')->assertForbidden();
});

test('the page is told which frame to wear', function () {
    $this->actingAs($this->agent)->get('/agent/farmers')
        ->assertInertia(fn($page) => $page->where('layout', 'agent')
            ->where('basePath', '/agent/farmers'));
});

test('the admin address wears the admin frame', function () {
    $this->actingAs($this->admin)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->where('layout', 'admin')
            ->where('basePath', '/admin/farmers'));
});

test('an agent sees only farmers assigned to them', function () {
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    FarmerProfile::factory()->count(2)->create();

    $this->actingAs($this->agent)->get('/agent/farmers')
        ->assertInertia(fn($page) => $page->has('farmers.data', 1));
});

test('an agent can open a farmer they hold', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->agent)->get("/agent/farmers/{$profile->uuid}")
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/Farmers/Show')
            ->where('layout', 'agent'));
});

test('an agent cannot open a farmer held by someone else', function () {
    $profile = FarmerProfile::factory()->create();

    $this->actingAs($this->agent)->get("/agent/farmers/{$profile->uuid}")->assertNotFound();
});

test('an agent can register a farmer here', function () {
    $this->actingAs($this->agent)->post('/agent/farmers', [
        'surname' => 'Mensah',
        'first_name' => 'Kwabena',
        'other_name' => null,
        'phone' => '0244445566',
        'gender' => 'male',
        'date_of_birth' => '1988-03-14',
        'home_address' => null,
        'community_id' => $this->community->id,
        'farmer_group_id' => null,
        'assigned_agent_id' => null,
        'farm_type_ids' => [$this->farmType->id],
    ])->assertSessionDoesntHaveErrors();

    expect(User::where('phone', '0244445566')->first()->farmerProfile->assigned_agent_id)
        ->toBe($this->agent->id);
});

test('an agent can capture a document here', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->agent)->post("/agent/farmers/{$profile->uuid}/identity", [
        'identity_type' => 'ghana_card',
        'identity_number' => 'GHA-123456789-0',
    ])->assertSessionDoesntHaveErrors();

    expect($profile->fresh()->identity_number_hash)->not->toBeNull();
});

// verifying is not an agent's job, so the address does not exist for them
test('an agent cannot verify here', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->agent)->patch("/agent/farmers/{$profile->uuid}/identity/verify")->assertNotFound();
});

test('an agent can complete a pending profile here', function () {
    $farmer = User::factory()->create(['phone' => '0277778899']);
    $farmer->assignRole('farmer');

    $this->actingAs($this->agent)->post("/agent/farmers/pending/{$farmer->id}", [
        'gender' => 'female',
        'date_of_birth' => '1992-06-01',
        'home_address' => null,
        'community_id' => $this->community->id,
        'farmer_group_id' => null,
        'assigned_agent_id' => null,
        'farm_type_ids' => [$this->farmType->id],
    ])->assertSessionDoesntHaveErrors();

    expect($farmer->fresh()->farmerProfile->assigned_agent_id)->toBe($this->agent->id);
});

test('completing here returns to the agent list', function () {
    $farmer = User::factory()->create(['phone' => '0277778899']);
    $farmer->assignRole('farmer');

    $this->actingAs($this->agent)->post("/agent/farmers/pending/{$farmer->id}", [
        'gender' => 'female',
        'date_of_birth' => '1992-06-01',
        'home_address' => null,
        'community_id' => $this->community->id,
        'farmer_group_id' => null,
        'assigned_agent_id' => null,
        'farm_type_ids' => [$this->farmType->id],
    ])->assertRedirect('/agent/farmers');
});

test('an agent is not offered the agent list here', function () {
    $this->actingAs($this->agent)->get('/agent/farmers')
        ->assertInertia(fn($page) => $page->has('agents', 0));
});

test('the farmers link appears for an agent', function () {
    $this->actingAs($this->agent)->get('/agent/farmers')
        ->assertInertia(fn($page) => $page->where('auth.nav', fn($nav) => collect($nav)->contains('agent.farmers.index')));
});
