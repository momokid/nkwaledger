<?php

use App\Enums\IdentityType;
use App\Models\AuditLog;
use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\IdentityDocument;
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

    $this->community = Community::factory()->create();
    $this->farmType = FarmType::factory()->withCategory()->create();

    session(['auth.password_confirmed_at' => now()->timestamp]);
});

function farmerPayload(array $overrides = []): array
{
    return array_merge([
        'surname' => 'Mensah',
        'first_name' => 'Kwabena',
        'other_name' => null,
        'phone' => '0244445566',
        'gender' => 'male',
        'date_of_birth' => '1988-03-14',
        'home_address' => 'House 4, Ayeduase',
        'community_id' => test()->community->id,
        'farmer_group_id' => null,
        'assigned_agent_id' => null,
        'farm_type_ids' => [test()->farmType->id],
    ], $overrides);
}

function editPayload(FarmerProfile $profile, array $overrides = []): array
{
    return array_merge([
        'gender' => 'male',
        'date_of_birth' => '1990-01-01',
        'home_address' => null,
        'community_id' => $profile->community_id,
        'farmer_group_id' => null,
        'assigned_agent_id' => $profile->assigned_agent_id,
        'farm_type_ids' => [test()->farmType->id],
        'is_active' => true,
    ], $overrides);
}

test('a guest is redirected to login', function () {
    $this->get('/admin/farmers')->assertRedirect('/login');
});

test('a user without the view permission is forbidden', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/admin/farmers')->assertForbidden();
});

test('an admin sees the list page', function () {
    $this->actingAs($this->admin)->get('/admin/farmers')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/Farmers/Index')->has('farmers'));
});

test('an agent sees the list page', function () {
    $this->actingAs($this->agent)->get('/admin/farmers')->assertOk();
});

test('registering creates a user with the farmer role', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload([
        'assigned_agent_id' => $this->agent->id,
    ]))->assertSessionDoesntHaveErrors();

    $user = User::where('phone', '0244445566')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('farmer'))->toBeTrue();
});

test('registering creates the profile', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload([
        'assigned_agent_id' => $this->agent->id,
    ]));

    $user = User::where('phone', '0244445566')->first();

    expect($user->farmerProfile)->not->toBeNull()
        ->and($user->farmerProfile->community_id)->toBe($this->community->id);
});

// every farmer gets one on the way in, since urls are built from it
test('registering gives the profile a uuid', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload());

    expect(User::where('phone', '0244445566')->first()->farmerProfile->uuid)->not->toBeNull();
});

test('registering stores the home address', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload([
        'assigned_agent_id' => $this->agent->id,
    ]));

    expect(User::where('phone', '0244445566')->first()->farmerProfile->home_address)
        ->toBe('House 4, Ayeduase');
});

test('registering attaches the chosen farm types', function () {
    $second = FarmType::factory()->withCategory()->create();

    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload([
        'assigned_agent_id' => $this->agent->id,
        'farm_type_ids' => [$this->farmType->id, $second->id],
    ]));

    expect(User::where('phone', '0244445566')->first()->farmerProfile->farmTypes)->toHaveCount(2);
});

test('registering records who typed the row', function () {
    $this->actingAs($this->agent)->post('/admin/farmers', farmerPayload());

    expect(User::where('phone', '0244445566')->first()->farmerProfile->registered_by)
        ->toBe($this->agent->id);
});

// an agent takes on the farmers they bring in, with no field to fill
test('an agent registering is assigned the farmer', function () {
    $this->actingAs($this->agent)->post('/admin/farmers', farmerPayload([
        'assigned_agent_id' => $this->otherAgent->id,
    ]));

    expect(User::where('phone', '0244445566')->first()->farmerProfile->assigned_agent_id)
        ->toBe($this->agent->id);
});

test('an admin registering assigns the chosen agent', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload([
        'assigned_agent_id' => $this->agent->id,
    ]));

    $profile = User::where('phone', '0244445566')->first()->farmerProfile;

    expect($profile->assigned_agent_id)->toBe($this->agent->id)
        ->and($profile->registered_by)->toBe($this->admin->id);
});

test('an admin may leave a farmer unassigned', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload())
        ->assertSessionDoesntHaveErrors();

    expect(User::where('phone', '0244445566')->first()->farmerProfile->assigned_agent_id)->toBeNull();
});

test('a farmer cannot be assigned to someone who is not an agent', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload([
        'assigned_agent_id' => $vet->id,
    ]))->assertSessionHasErrors('assigned_agent_id');
});

test('a registered farmer has no password', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload());

    expect(User::where('phone', '0244445566')->first()->password)->toBeNull();
});

test('a registered farmer starts unverified', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload());

    expect(User::where('phone', '0244445566')->first()->phone_verified_at)->toBeNull();
});

test('registering sends a phone verification code', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload());

    expect(OtpCode::where('identifier', '0244445566')
        ->where('type', 'phone_verification')
        ->exists())->toBeTrue();
});

test('a duplicate phone is refused', function () {
    User::factory()->create(['phone' => '0244445566']);

    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload())
        ->assertSessionHasErrors('phone');
});

test('a failed registration leaves no orphan user', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload([
        'community_id' => 999999,
    ]));

    expect(User::where('phone', '0244445566')->exists())->toBeFalse();
});

test('at least one farm type is required', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload(['farm_type_ids' => []]))
        ->assertSessionHasErrors('farm_type_ids');
});

test('a community is required', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload(['community_id' => null]))
        ->assertSessionHasErrors('community_id');
});

// revoking from the user leaves the role grant standing, so the case needs a role that never had it
test('a user without the create permission cannot register', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $vet->givePermissionTo('farmers.view');

    $this->actingAs($vet)->post('/admin/farmers', farmerPayload())->assertForbidden();
});

test('an admin sees every farmer', function () {
    FarmerProfile::factory()->count(3)->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('farmers.data', 3));
});

// the browser never sees the row id, so nothing can be found by counting upward
test('the list carries uuids rather than row ids', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->where('farmers.data.0.id', $profile->uuid));
});

test('an agent sees only farmers assigned to them', function () {
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    FarmerProfile::factory()->count(2)->create(['assigned_agent_id' => $this->otherAgent->id]);

    $this->actingAs($this->agent)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('farmers.data', 1));
});

// typing the row does not keep a farmer once they are handed to someone else
test('an agent does not see a farmer they registered but no longer hold', function () {
    FarmerProfile::factory()->create([
        'registered_by' => $this->agent->id,
        'assigned_agent_id' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('farmers.data', 0));
});

test('an agent cannot open a farmer assigned to someone else', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    $this->actingAs($this->agent)->get("/admin/farmers/{$profile->uuid}")->assertNotFound();
});

test('an agent cannot open an unassigned farmer', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => null]);

    $this->actingAs($this->agent)->get("/admin/farmers/{$profile->uuid}")->assertNotFound();
});

// the row id is not an address any more
test('a farmer cannot be reached by their row id', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->get("/admin/farmers/{$profile->id}")->assertNotFound();
});

test('an admin can open any farmer', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->get("/admin/farmers/{$profile->uuid}")
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/Farmers/Show'));
});

test('a farmer can be edited', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $newCommunity = Community::factory()->create();

    $this->actingAs($this->admin)->put("/admin/farmers/{$profile->uuid}", editPayload($profile, [
        'community_id' => $newCommunity->id,
    ]))->assertSessionDoesntHaveErrors();

    expect($profile->fresh()->community_id)->toBe($newCommunity->id);
});

test('editing replaces the farm types', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $profile->farmTypes()->attach($this->farmType->id);
    $replacement = FarmType::factory()->withCategory()->create();

    $this->actingAs($this->admin)->put("/admin/farmers/{$profile->uuid}", editPayload($profile, [
        'farm_type_ids' => [$replacement->id],
    ]));

    expect($profile->fresh()->farmTypes->pluck('id')->all())->toBe([$replacement->id]);
});

test('an admin can hand a farmer to another agent', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->put("/admin/farmers/{$profile->uuid}", editPayload($profile, [
        'assigned_agent_id' => $this->otherAgent->id,
    ]));

    expect($profile->fresh()->assigned_agent_id)->toBe($this->otherAgent->id);
});

// letting an agent reassign would let them empty their own list or take another agent's farmers
test('an agent cannot hand a farmer to another agent', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->agent)->put("/admin/farmers/{$profile->uuid}", editPayload($profile, [
        'assigned_agent_id' => $this->otherAgent->id,
    ]));

    expect($profile->fresh()->assigned_agent_id)->toBe($this->agent->id);
});

test('a farmer can be put on hold', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->put("/admin/farmers/{$profile->uuid}", editPayload($profile, [
        'is_active' => false,
    ]));

    expect($profile->fresh()->is_active)->toBeFalse();
});

// the address exists for reading and editing, so deleting is refused as a method
test('a farmer cannot be deleted', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->delete("/admin/farmers/{$profile->uuid}")->assertMethodNotAllowed();
});

test('an identity document can be captured', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$profile->uuid}/identity", [
        'identity_type' => 'ghana_card',
        'identity_number' => 'GHA-123456789-0',
    ])->assertSessionDoesntHaveErrors();

    expect($profile->fresh()->identity_number_hash)->toBe(IdentityDocument::hash('GHA-123456789-0'));
});

test('the raw identity number is never stored', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$profile->uuid}/identity", [
        'identity_type' => 'ghana_card',
        'identity_number' => 'GHA-123456789-0',
    ]);

    $this->assertDatabaseMissing('farmer_profiles', ['identity_number_hash' => 'GHA-123456789-0']);
});

test('an unknown identity type is refused', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$profile->uuid}/identity", [
        'identity_type' => 'drivers_licence',
        'identity_number' => 'ABC123',
    ])->assertSessionHasErrors('identity_type');
});

test('a document already used by another farmer is refused', function () {
    FarmerProfile::factory()->withIdentity(IdentityType::GhanaCard, 'GHA-123456789-0')->create();
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$profile->uuid}/identity", [
        'identity_type' => 'ghana_card',
        'identity_number' => 'GHA-123456789-0',
    ])->assertSessionHasErrors('identity_number');
});

test('capturing a document does not verify it', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$profile->uuid}/identity", [
        'identity_type' => 'ghana_card',
        'identity_number' => 'GHA-123456789-0',
    ]);

    expect($profile->fresh()->identity_verified_at)->toBeNull();
});

test('an admin can verify a captured document', function () {
    $profile = FarmerProfile::factory()->withIdentity()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$profile->uuid}/identity/verify")
        ->assertSessionDoesntHaveErrors();

    expect($profile->fresh()->identity_verified_at)->not->toBeNull();
});

test('verifying records who did it', function () {
    $profile = FarmerProfile::factory()->withIdentity()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$profile->uuid}/identity/verify");

    expect($profile->fresh()->identity_verified_by)->toBe($this->admin->id);
});

test('an agent cannot verify by default', function () {
    $profile = FarmerProfile::factory()->withIdentity()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$profile->uuid}/identity/verify")->assertForbidden();
});

// the agent who serves a farmer may not be the one who vouches for their document
test('the assigned agent cannot verify their own farmer', function () {
    $this->agent->givePermissionTo('farmers.verify');
    $profile = FarmerProfile::factory()->withIdentity()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$profile->uuid}/identity/verify")
        ->assertSessionHasErrors();

    expect($profile->fresh()->identity_verified_at)->toBeNull();
});

// with nobody assigned, whoever typed the row stands in as the conflicted party
test('an unassigned farmer cannot be verified by whoever registered them', function () {
    $profile = FarmerProfile::factory()->withIdentity()->create([
        'registered_by' => $this->admin->id,
        'assigned_agent_id' => null,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$profile->uuid}/identity/verify")
        ->assertSessionHasErrors();

    expect($profile->fresh()->identity_verified_at)->toBeNull();
});

test('an agent who only typed the row may still verify once someone else holds the farmer', function () {
    $this->agent->givePermissionTo('farmers.verify');
    $profile = FarmerProfile::factory()->withIdentity()->create([
        'registered_by' => $this->agent->id,
        'assigned_agent_id' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$profile->uuid}/identity/verify")
        ->assertSessionDoesntHaveErrors();

    expect($profile->fresh()->identity_verified_at)->not->toBeNull();
});

test('a farmer with no document cannot be verified', function () {
    $profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$profile->uuid}/identity/verify")
        ->assertSessionHasErrors();
});

test('verification is written to the audit log', function () {
    $profile = FarmerProfile::factory()->withIdentity()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$profile->uuid}/identity/verify");

    expect(AuditLog::where('action', 'farmer.identity_verified')->exists())->toBeTrue();
});

test('the agent list is offered to an admin', function () {
    $this->actingAs($this->admin)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('agents', 2));
});

test('an agent is not offered the agent list', function () {
    $this->actingAs($this->agent)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('agents', 0));
});

test('the page says what this user may do', function () {
    $this->actingAs($this->agent)->get('/admin/farmers')
        ->assertInertia(
            fn($page) => $page
                ->where('permissions.create', true)
                ->where('permissions.verify', false)
        );
});
