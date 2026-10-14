<?php

use App\Enums\OfficerRole;
use App\Models\AgentOfficerAssignment;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\Notification;
use App\Models\SyncSubmission;
use App\Models\User;
use App\Services\DiseaseReports\ReportRoutingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Storage::fake('public');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->vet = User::factory()->create();
    $this->vet->assignRole('vet');
    AgentOfficerAssignment::create(['agent_id' => $this->agent->id, 'officer_id' => $this->vet->id, 'role' => OfficerRole::Vet]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id, 'assigned_agent_id' => $this->agent->id]);

    $farmType = FarmType::factory()->withCategory(FarmTypeCategory::create(['name' => 'Livestock']))->create();
    $this->unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id, 'farm_type_id' => $farmType->id]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function healthRecord(array $over = []): array
{
    return array_merge([
        'type' => 'health_report',
        'uuid' => (string) Str::uuid(),
        'farmer' => test()->profile->uuid,
        'farm_unit_id' => test()->unit->id,
        'description' => 'Some birds look weak and are not eating.',
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ], $over);
}

function syncHealth(User $user, array ...$records): array
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => $records])->assertOk()->json('results');
}

function waiting(): ?DiseaseReport
{
    return DiseaseReport::withoutGlobalScopes()->first();
}

it('creates the report in the waiting state, not routed, with no photo yet', function () {
    $result = syncHealth($this->farmerUser, healthRecord())[0];

    expect($result['status'])->toBe('accepted');

    $report = waiting();

    expect($report->status->value)->toBe('waiting_for_photo')
        ->and($report->photo_path)->toBeNull()
        ->and($report->assigned_officer_id)->toBeNull()
        ->and($report->farm_unit_id)->toBe($this->unit->id)
        ->and($report->farmer_profile_id)->toBe($this->profile->id)
        ->and($report->category)->toBe('Livestock')
        ->and($report->routed_role)->toBe(OfficerRole::Vet)
        ->and($report->description)->toBe('Some birds look weak and are not eating.');
});

it('sends no notification to anybody', function () {
    syncHealth($this->farmerUser, healthRecord());

    expect(Notification::count())->toBe(0);
});

it('never shows a waiting report in any officer, admin or farmer list', function () {
    syncHealth($this->farmerUser, healthRecord());
    $report = waiting();

    $this->actingAs($this->admin)->get('/admin/disease-reports')->assertOk()
        ->assertInertia(fn($page) => $page->has('reports', 0));

    $this->actingAs($this->vet)->get('/vet/dashboard')->assertOk()
        ->assertInertia(fn($page) => $page->has('reports', 0));
    $this->actingAs($this->vet)->get("/vet/reports/{$report->uuid}")->assertNotFound();

    $this->actingAs($this->farmerUser)->get('/my-farm/reports')->assertOk()
        ->assertInertia(fn($page) => $page->has('reports', 0));
    $this->actingAs($this->farmerUser)->get("/my-farm/reports/{$report->uuid}")->assertNotFound();

    expect(DiseaseReport::count())->toBe(0);
});

it('does not hand a waiting report to an officer when one is linked later', function () {
    syncHealth($this->farmerUser, healthRecord());

    $moved = app(ReportRoutingService::class)->autoAssignWaitingReports($this->agent->id, OfficerRole::Vet, $this->vet);

    expect($moved)->toBe(0)->and(waiting()->assigned_officer_id)->toBeNull();
});

it('creates one report however many times the same record is sent', function () {
    $record = healthRecord();

    $first = syncHealth($this->farmerUser, $record)[0];
    $again = syncHealth($this->farmerUser, $record)[0];

    expect($again)->toBe($first)
        ->and(DiseaseReport::withoutGlobalScopes()->count())->toBe(1)
        ->and(SyncSubmission::count())->toBe(1);
});

it('refuses a reused uuid that carries other details', function () {
    $record = healthRecord();
    syncHealth($this->farmerUser, $record);

    $result = syncHealth($this->farmerUser, [...$record, 'description' => 'A different report.'])[0];

    expect($result['status'])->toBe('error')
        ->and(DiseaseReport::withoutGlobalScopes()->count())->toBe(1);
});

it('handles health reports and records in the order sent', function () {
    $results = syncHealth($this->farmerUser, healthRecord(['uuid' => $a = (string) Str::uuid()]), healthRecord(['uuid' => $b = (string) Str::uuid()]));

    expect(array_column($results, 'uuid'))->toBe([$a, $b])
        ->and(DiseaseReport::withoutGlobalScopes()->orderBy('id')->pluck('description')->count())->toBe(2);
});

it('links a report already posted through the online form instead of creating another', function () {
    $record = healthRecord();

    $this->actingAs($this->farmerUser)->post("/my-farm/{$this->unit->id}/report-problem", [
        'idempotency_key' => $record['uuid'],
        'description' => $record['description'],
        'photo' => UploadedFile::fake()->image('sick.jpg'),
    ])->assertRedirect();

    $result = syncHealth($this->farmerUser, $record)[0];

    expect($result['status'])->toBe('accepted')
        ->and(DiseaseReport::withoutGlobalScopes()->count())->toBe(1)
        ->and(DiseaseReport::first()->status->value)->toBe('new');
});

it('posts nothing online when the same record already arrived through sync', function () {
    $record = healthRecord();
    syncHealth($this->farmerUser, $record);

    $this->actingAs($this->farmerUser)->post("/my-farm/{$this->unit->id}/report-problem", [
        'idempotency_key' => $record['uuid'],
        'description' => $record['description'],
        'photo' => UploadedFile::fake()->image('sick.jpg'),
    ])->assertRedirect();

    expect(DiseaseReport::withoutGlobalScopes()->count())->toBe(1)
        ->and(Notification::count())->toBe(0);
});

it('refuses an agent, even the own agent of the farmer, as the online form does', function () {
    $this->actingAs($this->agent)->post("/my-farm/{$this->unit->id}/report-problem", [
        'description' => 'Weak birds.',
        'photo' => UploadedFile::fake()->image('sick.jpg'),
    ])->assertNotFound();

    $result = syncHealth($this->agent, healthRecord())[0];

    expect($result['status'])->toBe('needs_fixing')
        ->and($result['reason'])->toBe('A record could not be saved.')
        ->and(DiseaseReport::withoutGlobalScopes()->count())->toBe(0);
});

it('leaves reported_by empty', function () {
    syncHealth($this->farmerUser, healthRecord());

    expect(waiting()->reported_by)->toBeNull();
});

it('sends an empty description back as needing a fix, with the online message', function () {
    $result = syncHealth($this->farmerUser, healthRecord(['description' => '']))[0];

    expect($result['status'])->toBe('needs_fixing')
        ->and($result['reason'])->toBe('Please describe what you are seeing.')
        ->and(DiseaseReport::withoutGlobalScopes()->count())->toBe(0);
});

it('sends a description over 1000 characters back as needing a fix', function () {
    $result = syncHealth($this->farmerUser, healthRecord(['description' => str_repeat('a', 1001)]))[0];

    expect($result['status'])->toBe('needs_fixing')
        ->and(DiseaseReport::withoutGlobalScopes()->count())->toBe(0);
});

it('sends a report back as needing a fix when the sender may not report for that farmer or unit', function (string $who) {
    $other = User::factory()->create();
    $other->assignRole($who === 'vet' ? 'vet' : ($who === 'agent' ? 'agent' : 'farmer'));

    $result = syncHealth($other, healthRecord())[0];

    expect($result['status'])->toBe('needs_fixing')
        ->and($result['reason'])->toBe('A record could not be saved.')
        ->and(DiseaseReport::withoutGlobalScopes()->count())->toBe(0);
})->with(['another agent' => 'agent', 'another farmer' => 'farmer', 'a vet' => 'vet']);

it('sends a report back as needing a fix when the unit is not that farmer\'s', function () {
    $stranger = FarmUnit::factory()->create();

    $result = syncHealth($this->farmerUser, healthRecord(['farm_unit_id' => $stranger->id]))[0];

    expect($result['status'])->toBe('needs_fixing')->and($result['reason'])->toBe('A record could not be saved.')->and(DiseaseReport::withoutGlobalScopes()->count())->toBe(0);
});

it('gives the report form the public uuid of the farmer', function () {
    $this->actingAs($this->farmerUser)->get("/my-farm/{$this->unit->id}/report-problem")
        ->assertInertia(fn($page) => $page->where('farmer.id', $this->profile->uuid));
});

it('keeps health reports out of the sender\'s list of records', function () {
    syncHealth($this->farmerUser, healthRecord());

    $this->actingAs($this->farmerUser)->getJson('/sync/submissions')->assertOk()->assertJsonCount(0, 'data');
});

it('stores the online idempotency key on a report posted through the form', function () {
    $key = (string) Str::uuid();

    $this->actingAs($this->farmerUser)->post("/my-farm/{$this->unit->id}/report-problem", [
        'idempotency_key' => $key,
        'description' => 'Weak birds.',
        'photo' => UploadedFile::fake()->image('sick.jpg'),
    ])->assertRedirect();

    expect(DiseaseReport::first()->client_uuid)->toBe($key);
});

it('sends the generic notice, with no extra reason, when a refusal has none of its own', function () {
    syncHealth($this->agent, healthRecord());

    expect(Notification::where('kind', 'sync.needs_fixing')->pluck('message')->unique()->all())->toBe(['A record could not be saved.']);
});
