<?php

use App\Models\Community;
use App\Models\FarmerGroup;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Models\UserPermissionDenial;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->agent = User::factory()->create(['surname' => 'Agentson']);
    $this->agent->assignRole('agent');

    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    // group A holds two of this agent's farmers plus one belonging to another agent and one nobody holds
    $this->groupA = FarmerGroup::factory()->create(['name' => 'Kuapa Growers']);
    $this->mine1 = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id, 'farmer_group_id' => $this->groupA->id]);
    $this->mine2 = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id, 'farmer_group_id' => $this->groupA->id]);
    $this->theirs = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id, 'farmer_group_id' => $this->groupA->id]);
    $this->nobodys = FarmerProfile::factory()->create(['assigned_agent_id' => null, 'farmer_group_id' => $this->groupA->id]);

    // group B only holds another agent's farmer, group C is empty
    $this->groupB = FarmerGroup::factory()->create(['name' => 'Hidden Coop']);
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id, 'farmer_group_id' => $this->groupB->id]);
    $this->groupC = FarmerGroup::factory()->create(['name' => 'Empty Coop']);
});

test('a guest is redirected to login', function () {
    $this->get('/agent/farmer-groups')->assertRedirect('/login');
});

test('an agent sees only the groups that hold one of their assigned farmers', function () {
    $response = $this->actingAs($this->agent)->getJson('/agent/farmer-groups')->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$this->groupA->id]);
});

test('the list counts the agent\'s own farmers and everyone else\'s, nothing more', function () {
    $row = $this->actingAs($this->agent)->getJson('/agent/farmer-groups')->json('data.0');

    expect($row['name'])->toBe('Kuapa Growers')
        ->and($row['own_farmers_count'])->toBe(2)
        ->and($row['other_farmers_count'])->toBe(2);
});

test('a group can be narrowed to one community', function () {
    $community = Community::factory()->create();
    $other = FarmerGroup::factory()->create(['community_id' => $community->id]);
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id, 'farmer_group_id' => $other->id]);

    $ids = collect($this->actingAs($this->agent)->getJson("/agent/farmer-groups?community_id={$community->id}")->json('data'))
        ->pluck('id')->all();

    expect($ids)->toBe([$other->id]);
});

test('inside a group the agent sees full details for their own farmers', function () {
    $json = $this->actingAs($this->agent)->getJson("/agent/farmer-groups/{$this->groupA->id}")->assertOk();

    $own = collect($json->json('own_farmers'));

    expect($own->pluck('id')->sort()->values()->all())->toBe(collect([$this->mine1->uuid, $this->mine2->uuid])->sort()->values()->all());
    expect($own->first())->toHaveKeys(['id', 'name', 'phone', 'identity_verified']);
    expect($own->firstWhere('id', $this->mine1->uuid)['phone'])->toBe($this->mine1->user->phone);
});

test('other agents\' farmers, and unassigned ones, show as a count only', function () {
    $response = $this->actingAs($this->agent)->getJson("/agent/farmer-groups/{$this->groupA->id}")->assertOk();

    expect($response->json('other_farmers_count'))->toBe(2);

    $body = $response->getContent();

    foreach ([$this->theirs, $this->nobodys] as $hidden) {
        expect($body)->not->toContain($hidden->uuid)
            ->and($body)->not->toContain($hidden->user->phone)
            ->and($body)->not->toContain((string) $hidden->user->surname)
            ->and($body)->not->toContain((string) $hidden->user->first_name);
    }
});

test('a group that holds none of the agent\'s farmers is not found', function () {
    $this->actingAs($this->agent)->getJson("/agent/farmer-groups/{$this->groupB->id}")->assertNotFound();
    $this->actingAs($this->agent)->getJson("/agent/farmer-groups/{$this->groupC->id}")->assertNotFound();
});

test('holding the role is not enough, the agent needs farmer-groups.view-own itself', function () {
    UserPermissionDenial::create([
        'user_id' => $this->agent->id,
        'permission_id' => Permission::where('name', 'farmer-groups.view-own')->value('id'),
        'denied_by' => $this->admin->id,
    ]);

    $this->actingAs($this->agent)->getJson('/agent/farmer-groups')->assertForbidden();
    $this->actingAs($this->agent)->getJson("/agent/farmer-groups/{$this->groupA->id}")->assertForbidden();
});

test('a role without the permission is forbidden', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->getJson('/agent/farmer-groups')->assertForbidden();
});

test('an agent still cannot reach the admin group pages or the admin lookup', function () {
    $community = Community::factory()->create();

    $this->actingAs($this->agent)->get('/admin/farmer-groups')->assertForbidden();
    $this->actingAs($this->agent)->get("/admin/farmer-groups/by-community/{$community->id}")->assertForbidden();
});
