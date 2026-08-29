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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->community = Community::factory()->create();
    $this->farmType = FarmType::factory()->withCategory()->create();

    $this->selfRegistered = User::factory()->create([
        'surname' => 'Asante',
        'first_name' => 'Yaa',
        'phone' => '0277778899',
    ]);
    $this->selfRegistered->assignRole('farmer');
});

function completionPayload(array $overrides = []): array
{
    return array_merge([
        'gender' => 'female',
        'date_of_birth' => '1992-06-01',
        'home_address' => 'House 12, Ayigya',
        'community_id' => test()->community->id,
        'farmer_group_id' => null,
        'farm_type_ids' => [test()->farmType->id],
    ], $overrides);
}

test('a farmer without a profile appears as pending', function () {
    $this->actingAs($this->agent)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('pending', 1)
            ->where('pending.0.name', 'Asante Yaa'));
});

test('a farmer with a profile is not pending', function () {
    FarmerProfile::factory()->create(['user_id' => $this->selfRegistered->id]);

    $this->actingAs($this->agent)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('pending', 0));
});

test('a staff account is never pending', function () {
    $this->actingAs($this->agent)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->where('pending', fn($pending) => collect($pending)
            ->doesntContain(fn($row) => $row['name'] === "{$this->agent->surname} {$this->agent->first_name}")));
});

test('a pending farmer is visible to every agent', function () {
    $otherAgent = User::factory()->create();
    $otherAgent->assignRole('agent');

    $this->actingAs($otherAgent)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('pending', 1));
});

test('the completion page prefills the account details', function () {
    $this->actingAs($this->agent)->get("/admin/farmers/pending/{$this->selfRegistered->id}")
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/Farmers/Complete')
            ->where('account.surname', 'Asante')
            ->where('account.first_name', 'Yaa')
            ->where('account.phone', '0277778899'));
});

test('a user without the create permission cannot open the completion page', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $vet->givePermissionTo('farmers.view');

    $this->actingAs($vet)->get("/admin/farmers/pending/{$this->selfRegistered->id}")->assertForbidden();
});

test('a farmer who already has a profile cannot be completed', function () {
    FarmerProfile::factory()->create(['user_id' => $this->selfRegistered->id]);

    $this->actingAs($this->agent)->get("/admin/farmers/pending/{$this->selfRegistered->id}")->assertNotFound();
});

test('a staff account cannot be completed', function () {
    $this->actingAs($this->admin)->get("/admin/farmers/pending/{$this->agent->id}")->assertNotFound();
});

test('completing creates the profile', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload())
        ->assertSessionDoesntHaveErrors();

    expect($this->selfRegistered->fresh()->farmerProfile)->not->toBeNull();
});

test('completing records who did it', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload());

    expect($this->selfRegistered->fresh()->farmerProfile->registered_by)->toBe($this->agent->id);
});

test('completing stores the home address', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload());

    expect($this->selfRegistered->fresh()->farmerProfile->home_address)->toBe('House 12, Ayigya');
});

test('completing attaches the farm types', function () {
    $second = FarmType::factory()->withCategory()->create();

    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload([
        'farm_type_ids' => [$this->farmType->id, $second->id],
    ]));

    expect($this->selfRegistered->fresh()->farmerProfile->farmTypes)->toHaveCount(2);
});

// the account details belong to the farmer, an agent completing a profile must not be able to move them
test('completing cannot change the account details', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload([
        'surname' => 'Boateng',
        'phone' => '0244445566',
    ]));

    $user = $this->selfRegistered->fresh();

    expect($user->surname)->toBe('Asante')
        ->and($user->phone)->toBe('0277778899');
});

test('completing does not send a verification code', function () {
    $this->selfRegistered->forceFill(['phone_verified_at' => now()])->save();

    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload());

    expect(App\Models\OtpCode::where('identifier', '0277778899')->exists())->toBeFalse();
});

test('a community is required to complete', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload([
        'community_id' => null,
    ]))->assertSessionHasErrors('community_id');
});

test('at least one farm type is required to complete', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload([
        'farm_type_ids' => [],
    ]))->assertSessionHasErrors('farm_type_ids');
});

test('a farmer cannot be completed twice', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload());

    $this->actingAs($this->admin)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload())
        ->assertNotFound();

    expect(FarmerProfile::where('user_id', $this->selfRegistered->id)->count())->toBe(1);
});

test('a completed farmer leaves the pending list', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload());

    $this->actingAs($this->agent)->get('/admin/farmers')
        ->assertInertia(fn($page) => $page->has('pending', 0)->has('farmers.data', 1));
});

test('the home address is optional', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/pending/{$this->selfRegistered->id}", completionPayload([
        'home_address' => null,
    ]))->assertSessionDoesntHaveErrors();
});

test('groups can be listed for a community', function () {
    $this->actingAs($this->agent)->get("/admin/farmer-groups/by-community/{$this->community->id}")
        ->assertOk()
        ->assertJson([]);
});
