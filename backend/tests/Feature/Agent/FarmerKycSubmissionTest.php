<?php

use App\Models\AuditLog;
use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPermissionDenial;
use App\Services\ApprovalQueueService;
use App\Services\FarmerKycService;
use App\Support\IdentityDocument;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->otherAdmin = User::factory()->create();
    $this->otherAdmin->assignRole('admin');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    $this->farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
});

function kycPayload(array $overrides = []): array
{
    return array_merge([
        'identity_type' => 'ghana_card',
        'identity_number' => 'GHA-123456789-0',
    ], $overrides);
}

function submitAs(User $user, FarmerProfile $farmer, array $payload = [])
{
    return test()->actingAs($user)->post("/agent/farmers/{$farmer->uuid}/identity", kycPayload($payload));
}

test('an agent can submit a document for a farmer assigned to them', function () {
    submitAs($this->agent, $this->farmer)->assertSessionDoesntHaveErrors();

    $farmer = $this->farmer->fresh();

    expect($farmer->identity_number_hash)->toBe(IdentityDocument::hash('GHA-123456789-0'))
        ->and($farmer->identity_submitted_by)->toBe($this->agent->id)
        ->and($farmer->identity_submitted_at)->not->toBeNull()
        ->and($farmer->identity_verified_at)->toBeNull();
});

test('submitting is written to the audit log', function () {
    submitAs($this->agent, $this->farmer);

    expect(AuditLog::where('action', 'farmer.identity_captured')->exists())->toBeTrue();
});

test('submitting tells the admins who can approve it, but not the submitter', function () {
    submitAs($this->agent, $this->farmer);

    expect(Notification::where('user_id', $this->admin->id)->where('kind', 'farmer.kyc_submitted')->exists())->toBeTrue();
    expect(Notification::where('user_id', $this->agent->id)->where('kind', 'farmer.kyc_submitted')->exists())->toBeFalse();
});

test('an agent is forbidden from submitting for a farmer assigned to another agent', function () {
    $theirs = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    submitAs($this->agent, $theirs)->assertForbidden();

    expect($theirs->fresh()->identity_number_hash)->toBeNull()
        ->and($theirs->fresh()->identity_submitted_by)->toBeNull();
});

test('an agent is forbidden from submitting for an unassigned farmer', function () {
    $unassigned = FarmerProfile::factory()->create(['assigned_agent_id' => null]);

    submitAs($this->agent, $unassigned)->assertForbidden();

    expect($unassigned->fresh()->identity_number_hash)->toBeNull();
});

test('holding the role is not enough, the agent needs farmers.kyc-submit itself', function () {
    UserPermissionDenial::create([
        'user_id' => $this->agent->id,
        'permission_id' => Permission::where('name', 'farmers.kyc-submit')->value('id'),
        'denied_by' => $this->admin->id,
    ]);

    submitAs($this->agent, $this->farmer)->assertForbidden();

    expect($this->farmer->fresh()->identity_number_hash)->toBeNull();
});

test('the service itself refuses an out-of-scope agent, not just the route', function () {
    $theirs = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    expect(fn() => app(FarmerKycService::class)->submit($theirs, $this->agent, 'ghana_card', 'GHA-123456789-0'))
        ->toThrow(AuthorizationException::class);
});

test('a new submission clears an earlier verification', function () {
    $verified = FarmerProfile::factory()->verified()->create(['assigned_agent_id' => $this->agent->id]);

    submitAs($this->agent, $verified, ['identity_number' => 'GHA-987654321-0']);

    expect($verified->fresh()->identity_verified_at)->toBeNull()
        ->and($verified->fresh()->identity_verified_by)->toBeNull();
});

test('an agent cannot approve, even with farmers.verify granted to them directly', function () {
    $this->agent->givePermissionTo('farmers.verify');
    $profile = FarmerProfile::factory()->withIdentity()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$profile->uuid}/identity/verify")->assertForbidden();

    expect($profile->fresh()->identity_verified_at)->toBeNull();
});

test('there is no agent route for approving at all', function () {
    $this->agent->givePermissionTo('farmers.verify');
    $profile = FarmerProfile::factory()->withIdentity()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->agent)->patch("/agent/farmers/{$profile->uuid}/identity/verify")->assertNotFound();
});

test('the service refuses to approve for a non-admin who calls it directly', function () {
    $this->agent->givePermissionTo('farmers.verify');
    $profile = FarmerProfile::factory()->withIdentity()->create(['assigned_agent_id' => $this->otherAgent->id]);

    expect(fn() => app(FarmerKycService::class)->approve($profile, $this->agent))
        ->toThrow(AuthorizationException::class);

    expect($profile->fresh()->identity_verified_at)->toBeNull();
});

test('an admin other than the submitter can approve what an agent submitted', function () {
    submitAs($this->agent, $this->farmer);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/identity/verify")
        ->assertSessionDoesntHaveErrors();

    $farmer = $this->farmer->fresh();

    expect($farmer->identity_verified_at)->not->toBeNull()
        ->and($farmer->identity_verified_by)->toBe($this->admin->id);
    expect(AuditLog::where('action', 'farmer.identity_verified')->exists())->toBeTrue();
});

// the assigned agent is normally the conflicted party anyway, so this reassigns the farmer
// after the submission: only the submitter rule can be what stops the same person now
test('the submitter cannot approve their own submission even if they also hold admin', function () {
    $both = User::factory()->create();
    $both->assignRole(['agent', 'admin']);
    $mine = FarmerProfile::factory()->create(['assigned_agent_id' => $both->id]);

    submitAs($both, $mine)->assertSessionDoesntHaveErrors();
    expect($mine->fresh()->identity_submitted_by)->toBe($both->id);

    $mine->update(['assigned_agent_id' => $this->otherAgent->id]);
    expect($mine->fresh()->conflictedUserId())->not->toBe($both->id);

    $this->actingAs($both)->patch("/admin/farmers/{$mine->uuid}/identity/verify")
        ->assertSessionHasErrors('identity_number');

    expect($mine->fresh()->identity_verified_at)->toBeNull();

    expect(fn() => app(FarmerKycService::class)->approve($mine->fresh(), $both))
        ->toThrow(ValidationException::class);
});

test('an admin who submitted a document cannot approve it either', function () {
    $theirs = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    $this->actingAs($this->admin)->post("/admin/farmers/{$theirs->uuid}/identity", kycPayload())
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($this->admin)->patch("/admin/farmers/{$theirs->uuid}/identity/verify")
        ->assertSessionHasErrors('identity_number');

    expect($theirs->fresh()->identity_verified_at)->toBeNull();

    $this->actingAs($this->otherAdmin)->patch("/admin/farmers/{$theirs->uuid}/identity/verify")
        ->assertSessionDoesntHaveErrors();

    expect($theirs->fresh()->identity_verified_by)->toBe($this->otherAdmin->id);
});

test('a submitted document waits in the approval queue for an admin, and the submitter cannot act on it', function () {
    submitAs($this->agent, $this->farmer);

    $queue = app(ApprovalQueueService::class);

    $forAdmin = $queue->pending($this->admin)->firstWhere('kind', 'farmer_identity');
    expect($forAdmin)->not->toBeNull()
        ->and($forAdmin['farmer_id'])->toBe($this->farmer->uuid)
        ->and($forAdmin['can_approve'])->toBeTrue();

    // an agent has no farmers.verify, so approving is never on their list
    expect($queue->pending($this->agent)->firstWhere('kind', 'farmer_identity'))->toBeNull();

    $both = User::factory()->create();
    $both->assignRole(['agent', 'admin']);
    $mine = FarmerProfile::factory()->create(['assigned_agent_id' => $both->id]);
    submitAs($both, $mine, ['identity_number' => 'GHA-555555555-5'])->assertSessionDoesntHaveErrors();
    $mine->update(['assigned_agent_id' => $this->otherAgent->id]);

    $forBoth = $queue->pending($both)->where('kind', 'farmer_identity')->firstWhere('farmer_id', $mine->uuid);
    expect($forBoth['can_approve'])->toBeFalse();
});

test('a verified document is no longer in the queue', function () {
    submitAs($this->agent, $this->farmer);
    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/identity/verify");

    expect(app(ApprovalQueueService::class)->pending($this->otherAdmin)->firstWhere('kind', 'farmer_identity'))->toBeNull();
});
