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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

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
        'photo' => UploadedFile::fake()->image('farmer.jpg', 800, 600),
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

test('an agent gets a 404 submitting for a farmer assigned to another agent', function () {
    $theirs = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    submitAs($this->agent, $theirs)->assertNotFound();

    expect($theirs->fresh()->identity_number_hash)->toBeNull()
        ->and($theirs->fresh()->identity_submitted_by)->toBeNull();
});

test('an agent gets a 404 submitting for an unassigned farmer', function () {
    $unassigned = FarmerProfile::factory()->create(['assigned_agent_id' => null]);

    submitAs($this->agent, $unassigned)->assertNotFound();

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

    expect(fn() => app(FarmerKycService::class)->submit($theirs, $this->agent, 'ghana_card', 'GHA-123456789-0', UploadedFile::fake()->image('a.jpg')))
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

test('a photo of the farmer is required to submit', function () {
    submitAs($this->agent, $this->farmer, ['photo' => null])->assertSessionHasErrors('photo');

    expect($this->farmer->fresh()->identity_number_hash)->toBeNull()
        ->and($this->farmer->fresh()->identity_photo_path)->toBeNull();
});

test('the photo has to be an image', function () {
    submitAs($this->agent, $this->farmer, ['photo' => UploadedFile::fake()->create('id.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('photo');
});

test('the photo is kept on the private disk as a webp, never the public one', function () {
    submitAs($this->agent, $this->farmer)->assertSessionDoesntHaveErrors();

    $path = $this->farmer->fresh()->identity_photo_path;

    expect($path)->toStartWith('kyc/')->toEndWith('.webp');
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

test('a new submission replaces the photo and deletes the old file', function () {
    submitAs($this->agent, $this->farmer);
    $old = $this->farmer->fresh()->identity_photo_path;

    submitAs($this->agent, $this->farmer, ['identity_number' => 'GHA-987654321-0']);
    $new = $this->farmer->fresh()->identity_photo_path;

    expect($new)->not->toBe($old);
    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($new);
});

test('approval is impossible without a photo', function () {
    $profile = FarmerProfile::factory()->withIdentity()->create([
        'assigned_agent_id' => $this->agent->id,
        'identity_photo_path' => null,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$profile->uuid}/identity/verify")
        ->assertSessionHasErrors('photo');

    expect(fn() => app(FarmerKycService::class)->approve($profile, $this->admin))->toThrow(ValidationException::class);
    expect($profile->fresh()->identity_verified_at)->toBeNull();
});

function rejectAs(User $user, FarmerProfile $farmer, array $payload = ['reason' => 'The photo is blurry.'])
{
    return test()->actingAs($user)->patch("/admin/farmers/{$farmer->uuid}/identity/reject", $payload);
}

test('an admin can reject a submission, giving a reason', function () {
    submitAs($this->agent, $this->farmer);

    rejectAs($this->admin, $this->farmer)->assertSessionDoesntHaveErrors();

    $farmer = $this->farmer->fresh();

    expect($farmer->identity_rejected_reason)->toBe('The photo is blurry.')
        ->and($farmer->identity_rejected_by)->toBe($this->admin->id)
        ->and($farmer->identity_rejected_at)->not->toBeNull()
        ->and($farmer->identity_verified_at)->toBeNull();
    expect(AuditLog::where('action', 'farmer.identity_rejected')->exists())->toBeTrue();
});

test('a reason is required to reject', function () {
    submitAs($this->agent, $this->farmer);

    rejectAs($this->admin, $this->farmer, ['reason' => ''])->assertSessionHasErrors('reason');

    expect($this->farmer->fresh()->identity_rejected_at)->toBeNull();
});

test('a rejected submission leaves the queue and cannot be approved', function () {
    submitAs($this->agent, $this->farmer);
    rejectAs($this->admin, $this->farmer);

    expect(app(ApprovalQueueService::class)->pending($this->otherAdmin)->firstWhere('kind', 'farmer_identity'))->toBeNull();

    $this->actingAs($this->otherAdmin)->patch("/admin/farmers/{$this->farmer->uuid}/identity/verify")
        ->assertSessionHasErrors('identity_number');

    expect($this->farmer->fresh()->identity_verified_at)->toBeNull();
});

test('an agent cannot reject, on any route', function () {
    $this->agent->givePermissionTo('farmers.verify');
    submitAs($this->agent, $this->farmer);

    rejectAs($this->agent, $this->farmer)->assertForbidden();
    $this->actingAs($this->agent)->patch("/agent/farmers/{$this->farmer->uuid}/identity/reject", ['reason' => 'x'])->assertNotFound();

    expect(fn() => app(FarmerKycService::class)->reject($this->farmer->fresh(), $this->agent, 'x'))
        ->toThrow(AuthorizationException::class);
    expect($this->farmer->fresh()->identity_rejected_at)->toBeNull();
});

test('the same people who cannot approve cannot reject either', function () {
    // the admin who submitted it
    $theirs = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);
    $this->actingAs($this->admin)->post("/admin/farmers/{$theirs->uuid}/identity", kycPayload());
    rejectAs($this->admin, $theirs)->assertSessionHasErrors('identity_number');
    expect($theirs->fresh()->identity_rejected_at)->toBeNull();

    // the admin who holds the farmer
    $held = FarmerProfile::factory()->withIdentity()->create(['assigned_agent_id' => $this->otherAdmin->id]);
    rejectAs($this->otherAdmin, $held)->assertSessionHasErrors('identity_number');
    expect($held->fresh()->identity_rejected_at)->toBeNull();

    // someone else can
    rejectAs($this->otherAdmin, $theirs)->assertSessionDoesntHaveErrors();
    expect($theirs->fresh()->identity_rejected_at)->not->toBeNull();
});

test('the agent sees the reason on the farmer page, and can submit again', function () {
    submitAs($this->agent, $this->farmer);
    rejectAs($this->admin, $this->farmer);

    $this->actingAs($this->agent)->get("/agent/farmers/{$this->farmer->uuid}")
        ->assertInertia(fn($page) => $page->where('farmer.identity_rejected_reason', 'The photo is blurry.'));

    submitAs($this->agent, $this->farmer, ['identity_number' => 'GHA-987654321-0'])->assertSessionDoesntHaveErrors();

    $farmer = $this->farmer->fresh();

    expect($farmer->identity_rejected_at)->toBeNull()
        ->and($farmer->identity_rejected_reason)->toBeNull()
        ->and($farmer->identity_rejected_by)->toBeNull();
    expect(app(ApprovalQueueService::class)->pending($this->otherAdmin)->firstWhere('kind', 'farmer_identity'))->not->toBeNull();
});

test('the admin sees the photo on the approval row and on the farmer page', function () {
    submitAs($this->agent, $this->farmer);
    $url = "/farmers/{$this->farmer->uuid}/identity-photo";

    expect(app(ApprovalQueueService::class)->pending($this->admin)->firstWhere('kind', 'farmer_identity')['photo_urls'])->toBe([$url]);

    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}")
        ->assertInertia(fn($page) => $page->where('farmer.identity_photo_url', $url));
});

test('the photo is served to the agent who holds the farmer and to an admin', function () {
    submitAs($this->agent, $this->farmer);
    $url = "/farmers/{$this->farmer->uuid}/identity-photo";

    $this->actingAs($this->agent)->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->actingAs($this->admin)->get($url)->assertOk();
});

test('everyone else gets a 404 for the photo', function () {
    submitAs($this->agent, $this->farmer);
    $url = "/farmers/{$this->farmer->uuid}/identity-photo";

    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($this->otherAgent)->get($url)->assertNotFound();
    $this->actingAs($vet)->get($url)->assertNotFound();
    $this->actingAs($this->farmer->user)->get($url)->assertNotFound();
});

test('a guest is sent to login for the photo', function () {
    $this->get("/farmers/{$this->farmer->uuid}/identity-photo")->assertRedirect('/login');
});

test('a farmer with no photo on file is a 404 even for an admin', function () {
    $this->actingAs($this->admin)->get("/farmers/{$this->farmer->uuid}/identity-photo")->assertNotFound();
});

test('a failed save deletes the new photo and keeps the old one', function () {
    submitAs($this->agent, $this->farmer);
    $old = $this->farmer->fresh()->identity_photo_path;
    $before = Storage::disk('local')->allFiles('kyc');

    $failing = new class extends FarmerProfile {
        public function save(array $options = [])
        {
            throw new RuntimeException('database down');
        }
    };
    $failing->setRawAttributes($this->farmer->fresh()->getAttributes(), true);
    $failing->exists = true;

    expect(fn() => app(FarmerKycService::class)->submit($failing, $this->agent, 'ghana_card', 'GHA-987654321-0', UploadedFile::fake()->image('n.jpg')))
        ->toThrow(RuntimeException::class);

    expect(Storage::disk('local')->allFiles('kyc'))->toBe($before);
    Storage::disk('local')->assertExists($old);
});

test('rejecting shows the admin a success message', function () {
    submitAs($this->agent, $this->farmer);

    rejectAs($this->admin, $this->farmer)->assertSessionHas('success', 'ID verification rejected.');
});

test('rejecting tells the currently assigned agent, not the submitter', function () {
    submitAs($this->agent, $this->farmer);
    $this->farmer->update(['assigned_agent_id' => $this->otherAgent->id]);

    rejectAs($this->admin, $this->farmer, ['reason' => 'Photo is blurry']);

    $user = $this->farmer->user;
    $note = Notification::where('user_id', $this->otherAgent->id)->where('kind', 'farmer.kyc_rejected')->first();

    expect($note->message)->toBe("ID verification rejected for {$user->surname} {$user->first_name}. Reason: Photo is blurry.")
        ->and($note->link)->toBe("/agent/farmers/{$this->farmer->uuid}")
        ->and(Notification::where('user_id', $this->agent->id)->exists())->toBeFalse();
});

test('with no assigned agent, rejecting tells the other admins with a link and the submitter in text only', function () {
    submitAs($this->agent, $this->farmer);
    $this->farmer->update(['assigned_agent_id' => null]);

    rejectAs($this->admin, $this->farmer, ['reason' => 'Photo is blurry'])->assertSessionDoesntHaveErrors();

    $user = $this->farmer->user;
    $text = "ID verification rejected for {$user->surname} {$user->first_name}. Reason: Photo is blurry.";

    $adminNote = Notification::where('user_id', $this->otherAdmin->id)->where('kind', 'farmer.kyc_rejected')->first();
    $submitterNote = Notification::where('user_id', $this->agent->id)->where('kind', 'farmer.kyc_rejected')->first();

    expect($adminNote->message)->toBe($text)
        ->and($adminNote->link)->toBe("/admin/farmers/{$this->farmer->uuid}")
        ->and($submitterNote->message)->toBe($text)
        ->and($submitterNote->link)->toBeNull()
        ->and(Notification::where('user_id', $this->admin->id)->where('kind', 'farmer.kyc_rejected')->exists())->toBeFalse();
});

test('nobody is notified when a rejection is refused', function () {
    submitAs($this->agent, $this->farmer);

    rejectAs($this->admin, $this->farmer, ['reason' => ''])->assertSessionHasErrors('reason');

    expect(Notification::where('kind', 'farmer.kyc_rejected')->exists())->toBeFalse();
});

test('the approvals list can be reloaded on its own', function () {
    submitAs($this->agent, $this->farmer);

    $this->actingAs($this->admin)->get('/admin/approvals')
        ->assertInertia(fn($page) => $page->reloadOnly(
            ['items', 'flash', 'auth.pendingApprovals'],
            fn($reload) => $reload->has('items.data', 1)->has('auth.pendingApprovals')->missing('permissions')->missing('basePath'),
        ));
});

test('the photo goes to the configured photo disk', function () {
    config(['filesystems.photo_disk' => 'photos']);
    Storage::fake('photos');

    submitAs($this->agent, $this->farmer)->assertSessionDoesntHaveErrors();

    Storage::disk('photos')->assertExists($this->farmer->fresh()->identity_photo_path);
    Storage::disk('local')->assertDirectoryEmpty('kyc');
});
