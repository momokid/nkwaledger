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
        'community_id' => test()->community->id,
        'farmer_group_id' => null,
        'farm_type_ids' => [test()->farmType->id],
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
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload())
        ->assertSessionDoesntHaveErrors();

    $user = User::where('phone', '0244445566')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('farmer'))->toBeTrue();
});

test('registering creates the profile', function () {
    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload());

    $user = User::where('phone', '0244445566')->first();

    expect($user->farmerProfile)->not->toBeNull()
        ->and($user->farmerProfile->community_id)->toBe($this->community->id);
});

test('registering attaches the chosen farm types', function () {
    $second = FarmType::factory()->withCategory()->create();

    $this->actingAs($this->admin)->post('/admin/farmers', farmerPayload([
        'farm_type_ids' => [$this->farmType->id, $second->id],
    ]));

    expect(User::where('phone', '0244445566')->first()->farmerProfile->farmTypes)->toHaveCount(2);
});

test('registering records who did it', function () {
    $this->actingAs($this->agent)->post('/admin/farmers', farmerPayload());

    expect(User::where('phone', '0244445566')->first()->farmerProfile->registered_by)
        ->toBe($this->agent->id);
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

// a role that carries view but not create, since revoking from the user leaves the role grant standing
test('a user without the create permission cannot register', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $vet->givePermissionTo('farmers.view');

    $this->actingAs($vet)->post('/admin/farmers', farmerPayload())->assertForbidden();
});

test('an admin sees every farmer', function () {
    FarmerProfile::factory()->count(3)->create(['registered_by' => $this->agent->id]);

    $this->actingAs($this->admin)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('farmers.data', 3));
});

test('an agent sees only farmers they registered', function () {
    FarmerProfile::factory()->create(['registered_by' => $this->agent->id]);
    FarmerProfile::factory()->count(2)->create(['registered_by' => $this->admin->id]);

    $this->actingAs($this->agent)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('farmers.data', 1));
});

test('an agent cannot open a farmer they did not register', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->admin->id]);

    $this->actingAs($this->agent)->get("/admin/farmers/{$profile->id}")->assertForbidden();
});

test('an admin can open any farmer', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->agent->id]);

    $this->actingAs($this->admin)->get("/admin/farmers/{$profile->id}")
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/Farmers/Show'));
});

test('a farmer can be edited', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->admin->id]);
    $newCommunity = Community::factory()->create();

    $this->actingAs($this->admin)->put("/admin/farmers/{$profile->id}", [
        'gender' => 'female',
        'date_of_birth' => '1990-01-01',
        'community_id' => $newCommunity->id,
        'farmer_group_id' => null,
        'farm_type_ids' => [$this->farmType->id],
        'is_active' => true,
    ])->assertSessionDoesntHaveErrors();

    expect($profile->fresh()->community_id)->toBe($newCommunity->id);
});

test('editing replaces the farm types', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->admin->id]);
    $profile->farmTypes()->attach($this->farmType->id);
    $replacement = FarmType::factory()->withCategory()->create();

    $this->actingAs($this->admin)->put("/admin/farmers/{$profile->id}", [
        'gender' => 'male',
        'date_of_birth' => '1990-01-01',
        'community_id' => $profile->community_id,
        'farmer_group_id' => null,
        'farm_type_ids' => [$replacement->id],
        'is_active' => true,
    ]);

    expect($profile->fresh()->farmTypes->pluck('id')->all())->toBe([$replacement->id]);
});

test('a farmer can be put on hold', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->admin->id]);

    $this->actingAs($this->admin)->put("/admin/farmers/{$profile->id}", [
        'gender' => 'male',
        'date_of_birth' => '1990-01-01',
        'community_id' => $profile->community_id,
        'farmer_group_id' => null,
        'farm_type_ids' => [$this->farmType->id],
        'is_active' => false,
    ]);

    expect($profile->fresh()->is_active)->toBeFalse();
});

// the address exists for reading and editing, so deleting is refused as a method, not hidden
test('a farmer cannot be deleted', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->admin->id]);

    $this->actingAs($this->admin)->delete("/admin/farmers/{$profile->id}")->assertMethodNotAllowed();
});

test('an identity document can be captured', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->admin->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$profile->id}/identity", [
        'identity_type' => 'ghana_card',
        'identity_number' => 'GHA-123456789-0',
    ])->assertSessionDoesntHaveErrors();

    expect($profile->fresh()->identity_number_hash)->toBe(IdentityDocument::hash('GHA-123456789-0'));
});

test('the raw identity number is never stored', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->admin->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$profile->id}/identity", [
        'identity_type' => 'ghana_card',
        'identity_number' => 'GHA-123456789-0',
    ]);

    $this->assertDatabaseMissing('farmer_profiles', ['identity_number_hash' => 'GHA-123456789-0']);
});

test('an unknown identity type is refused', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->admin->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$profile->id}/identity", [
        'identity_type' => 'drivers_licence',
        'identity_number' => 'ABC123',
    ])->assertSessionHasErrors('identity_type');
});

test('a document already used by another farmer is refused', function () {
    FarmerProfile::factory()->withIdentity(IdentityType::GhanaCard, 'GHA-123456789-0')->create();
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->admin->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$profile->id}/identity", [
        'identity_type' => 'ghana_card',
        'identity_number' => 'GHA-123456789-0',
    ])->assertSessionHasErrors('identity_number');
});

test('capturing a document does not verify it', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->admin->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$profile->id}/identity", [
        'identity_type' => 'ghana_card',
        'identity_number' => 'GHA-123456789-0',
    ]);

    expect($profile->fresh()->identity_verified_at)->toBeNull();
});

test('an admin can verify a captured document', function () {
    $profile = FarmerProfile::factory()->withIdentity()->create(['registered_by' => $this->agent->id]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$profile->id}/identity/verify")
        ->assertSessionDoesntHaveErrors();

    expect($profile->fresh()->identity_verified_at)->not->toBeNull();
});

test('verifying records who did it', function () {
    $profile = FarmerProfile::factory()->withIdentity()->create(['registered_by' => $this->agent->id]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$profile->id}/identity/verify");

    expect($profile->fresh()->identity_verified_by)->toBe($this->admin->id);
});

test('an agent cannot verify by default', function () {
    $profile = FarmerProfile::factory()->withIdentity()->create(['registered_by' => $this->agent->id]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$profile->id}/identity/verify")->assertForbidden();
});

test('the person who registered a farmer cannot verify them', function () {
    $this->agent->givePermissionTo('farmers.verify');
    $profile = FarmerProfile::factory()->withIdentity()->create(['registered_by' => $this->agent->id]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$profile->id}/identity/verify")
        ->assertSessionHasErrors();

    expect($profile->fresh()->identity_verified_at)->toBeNull();
});

test('a farmer with no document cannot be verified', function () {
    $profile = FarmerProfile::factory()->create(['registered_by' => $this->agent->id]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$profile->id}/identity/verify")
        ->assertSessionHasErrors();
});

test('verification is written to the audit log', function () {
    $profile = FarmerProfile::factory()->withIdentity()->create(['registered_by' => $this->agent->id]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$profile->id}/identity/verify");

    expect(AuditLog::where('action', 'farmer.identity_verified')->exists())->toBeTrue();
});

test('the page says what this user may do', function () {
    $this->actingAs($this->agent)->get('/admin/farmers')
        ->assertInertia(
            fn($page) => $page
                ->where('permissions.create', true)
                ->where('permissions.verify', false)
        );
});
