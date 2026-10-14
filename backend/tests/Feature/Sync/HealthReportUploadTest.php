<?php

use App\Enums\OfficerRole;
use App\Models\AgentOfficerAssignment;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
    config(['filesystems.photo_disk' => 'local', 'health_reports.chunk_size' => 1024, 'health_reports.requests_per_minute' => 100000]);

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

function hrWaiting(?User $user = null): string
{
    $user ??= test()->farmerUser;
    $uuid = (string) Str::uuid();

    test()->actingAs($user)->postJson('/sync/submissions', ['records' => [[
        'type' => 'health_report',
        'uuid' => $uuid,
        'farmer' => test()->profile->uuid,
        'farm_unit_id' => test()->unit->id,
        'description' => 'Some birds look weak.',
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ]]])->assertOk();

    return $uuid;
}

function hrPhotoBytes(int $w = 300, int $h = 200): string
{
    $file = UploadedFile::fake()->image('p.jpg', $w, $h);

    return file_get_contents($file->getRealPath());
}

function hrVoiceBytes(int $seconds = 5): string
{
    $file = fakeAudioUpload($seconds);

    return file_get_contents($file->getRealPath());
}

function hrUrl(string $uuid, string $kind): string
{
    return "/sync/health-reports/{$uuid}/{$kind}";
}

function hrOpen(string $uuid, string $kind, string $bytes, ?User $user = null, ?string $sha = null)
{
    return test()->actingAs($user ?? test()->farmerUser)->postJson(hrUrl($uuid, $kind), [
        'size' => strlen($bytes),
        'sha256' => $sha ?? hash('sha256', $bytes),
    ]);
}

function hrChunk(string $uuid, string $kind, string $bytes, int $offset, ?int $length = null, ?User $user = null)
{
    $piece = substr($bytes, $offset, $length ?? config('health_reports.chunk_size'));

    return test()->actingAs($user ?? test()->farmerUser)->call(
        'PUT',
        hrUrl($uuid, $kind),
        [],
        [],
        [],
        ['HTTP_X_UPLOAD_OFFSET' => (string) $offset, 'CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'],
        $piece,
    );
}

// the whole protocol: open, then every chunk from the server's own offset
function hrSend(string $uuid, string $kind, string $bytes, ?User $user = null)
{
    $offset = hrOpen($uuid, $kind, $bytes, $user)->assertSuccessful()->json('offset');
    $last = null;

    while ($offset < strlen($bytes)) {
        $last = hrChunk($uuid, $kind, $bytes, $offset, null, $user);
        $last->assertOk();

        if ($last->json('state') === 'complete') {
            break;
        }

        $offset = $last->json('offset');
    }

    return $last;
}

function hrReport(): DiseaseReport
{
    return DiseaseReport::withoutGlobalScopes()->firstOrFail();
}

it('answers 404 to anyone but the report\'s own farmer', function (string $who) {
    $uuid = hrWaiting();
    $other = User::factory()->create();
    $other->assignRole($who);

    $user = $who === 'agent' ? test()->agent : $other;

    $this->actingAs($user)->getJson(hrUrl($uuid, 'photo'))->assertNotFound();
    hrOpen($uuid, 'photo', hrPhotoBytes(), $user)->assertNotFound();
    hrChunk($uuid, 'photo', hrPhotoBytes(), 0, null, $user)->assertNotFound();
})->with(['another farmer' => 'farmer', 'the agent' => 'agent', 'a vet' => 'vet']);

it('answers 404 for a report that does not exist', function () {
    $this->actingAs($this->farmerUser)->getJson(hrUrl((string) Str::uuid(), 'photo'))->assertNotFound();
});

it('refuses a guest', function () {
    $this->getJson(hrUrl((string) Str::uuid(), 'photo'))->assertUnauthorized();
});

it('tells the phone where the server is, and starts at zero', function () {
    $uuid = hrWaiting();

    $this->actingAs($this->farmerUser)->getJson(hrUrl($uuid, 'photo'))
        ->assertOk()->assertJson(['state' => 'none', 'offset' => 0, 'chunk_size' => 1024]);

    hrOpen($uuid, 'photo', hrPhotoBytes())->assertSuccessful()->assertJson(['state' => 'open', 'offset' => 0, 'chunk_size' => 1024]);
});

it('stores a photo sent in chunks, converted to webp and kept private', function () {
    config(['health_reports.chunk_size' => 65536]);
    $uuid = hrWaiting();
    $bytes = hrPhotoBytes(3000, 2000);

    hrSend($uuid, 'photo', $bytes)->assertJson(['state' => 'complete']);

    $report = hrReport();
    Storage::disk('local')->assertExists($report->photo_path);
    Storage::disk('public')->assertMissing($report->photo_path);
    expect($report->photo_path)->toEndWith('.webp')
        ->and(Storage::disk('local')->size($report->photo_path))->toBeLessThan(strlen($bytes))
        ->and($report->media_disk)->toBe('local');
});

it('resumes from the server\'s offset after the connection drops', function () {
    $uuid = hrWaiting();
    $bytes = hrPhotoBytes(800, 600);
    expect(strlen($bytes))->toBeGreaterThan(3 * 1024);

    hrOpen($uuid, 'photo', $bytes)->assertSuccessful();
    hrChunk($uuid, 'photo', $bytes, 0)->assertOk();
    hrChunk($uuid, 'photo', $bytes, 1024)->assertOk();

    // the phone lost its place: it asks, and the server says 2048
    $this->actingAs($this->farmerUser)->getJson(hrUrl($uuid, 'photo'))->assertJson(['state' => 'open', 'offset' => 2048]);

    // opening again with the same file changes nothing
    hrOpen($uuid, 'photo', $bytes)->assertJson(['offset' => 2048]);

    for ($offset = 2048; $offset < strlen($bytes); $offset += 1024) {
        hrChunk($uuid, 'photo', $bytes, $offset)->assertOk();
    }

    expect(hrReport()->photo_path)->not->toBeNull();
});

it('refuses a chunk at the wrong offset and says where the server is', function () {
    $uuid = hrWaiting();
    $bytes = hrPhotoBytes(800, 600);

    hrOpen($uuid, 'photo', $bytes)->assertSuccessful();
    hrChunk($uuid, 'photo', $bytes, 0)->assertOk();

    hrChunk($uuid, 'photo', $bytes, 4096)->assertStatus(409)->assertJson(['offset' => 1024]);
    hrChunk($uuid, 'photo', $bytes, 0)->assertStatus(409)->assertJson(['offset' => 1024]);

    $this->actingAs($this->farmerUser)->getJson(hrUrl($uuid, 'photo'))->assertJson(['offset' => 1024]);
});

it('refuses a chunk bigger than the fixed size', function () {
    $uuid = hrWaiting();
    $bytes = hrPhotoBytes(800, 600);

    hrOpen($uuid, 'photo', $bytes)->assertSuccessful();

    hrChunk($uuid, 'photo', $bytes, 0, 2048)->assertStatus(413);
});

it('refuses a file whose checksum does not match, and starts over', function () {
    $uuid = hrWaiting();
    $bytes = hrPhotoBytes();

    hrOpen($uuid, 'photo', $bytes, null, hash('sha256', 'something else'))->assertSuccessful();
    $offset = 0;
    $last = null;

    while ($offset < strlen($bytes)) {
        $last = hrChunk($uuid, 'photo', $bytes, $offset);
        $offset += 1024;
    }

    $last->assertStatus(422);
    expect(hrReport()->photo_path)->toBeNull();
    $this->actingAs($this->farmerUser)->getJson(hrUrl($uuid, 'photo'))->assertJson(['state' => 'none', 'offset' => 0]);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses a file that is not a photo by what it holds, whatever it is called or claimed to be', function () {
    $uuid = hrWaiting();
    $bytes = str_repeat('not a picture at all. ', 80);

    $offset = hrOpen($uuid, 'photo', $bytes)->assertSuccessful()->json('offset');
    $last = null;

    while ($offset < strlen($bytes)) {
        $last = hrChunk($uuid, 'photo', $bytes, $offset);
        $offset += 1024;
    }

    $last->assertStatus(422);
    expect(hrReport()->photo_path)->toBeNull()->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('accepts a real photo however the upload labels it', function () {
    $uuid = hrWaiting();
    $bytes = hrPhotoBytes();

    $this->actingAs($this->farmerUser)->postJson(hrUrl($uuid, 'photo'), ['size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)])->assertSuccessful();

    foreach (str_split($bytes, 1024) as $i => $piece) {
        $this->actingAs($this->farmerUser)->call('PUT', hrUrl($uuid, 'photo'), [], [], [], ['HTTP_X_UPLOAD_OFFSET' => (string) ($i * 1024), 'CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json'], $piece)->assertOk();
    }

    expect(hrReport()->photo_path)->not->toBeNull();
});

it('refuses a file over the size limit before taking any of it', function () {
    config(['health_reports.photo_max_bytes' => 1000, 'health_reports.audio_max_bytes' => 1000]);
    $uuid = hrWaiting();

    hrOpen($uuid, 'photo', hrPhotoBytes(800, 600))->assertStatus(422);
    hrOpen($uuid, 'audio', hrVoiceBytes())->assertSuccessful();

    hrOpen($uuid, 'audio', str_repeat('a', 1001))->assertStatus(422);
});

it('refuses a voice note that is not audio, or runs too long', function () {
    $uuid = hrWaiting();

    $png = hrPhotoBytes();
    hrOpen($uuid, 'audio', $png)->assertSuccessful();
    foreach (str_split($png, 1024) as $i => $piece) {
        $last = hrChunk($uuid, 'audio', $png, $i * 1024);
    }
    $last->assertStatus(422);

    $long = hrVoiceBytes(120);
    hrOpen($uuid, 'audio', $long)->assertSuccessful();
    $last = hrChunk($uuid, 'audio', $long, 0, 1024);
    foreach (range(1, (int) ceil(strlen($long) / 1024) - 1) as $i) {
        $last = hrChunk($uuid, 'audio', $long, $i * 1024);
    }
    expect(hrReport()->audio_path)->toBeNull();
});

it('does nothing the second time the upload is completed', function () {
    $uuid = hrWaiting();
    $bytes = hrPhotoBytes();

    hrSend($uuid, 'photo', $bytes);
    $path = hrReport()->photo_path;
    $notifications = Notification::count();

    hrChunk($uuid, 'photo', $bytes, (int) floor((strlen($bytes) - 1) / 1024) * 1024)->assertOk()->assertJson(['state' => 'complete']);
    hrOpen($uuid, 'photo', $bytes)->assertSuccessful()->assertJson(['state' => 'complete']);

    expect(hrReport()->photo_path)->toBe($path)
        ->and(Notification::count())->toBe($notifications)
        ->and(Storage::disk('local')->allFiles())->toBe([$path]);
});

it('keeps the report hidden, and its partial file unseen, until the photo is stored', function () {
    $uuid = hrWaiting();
    $report = hrReport();
    $bytes = hrPhotoBytes(800, 600);

    hrOpen($uuid, 'photo', $bytes)->assertSuccessful();
    hrChunk($uuid, 'photo', $bytes, 0)->assertOk();

    expect(DiseaseReport::count())->toBe(0);
    $this->actingAs($this->vet)->get('/vet/dashboard')->assertInertia(fn($page) => $page->has('reports', 0));
    $this->actingAs($this->admin)->get('/admin/disease-reports')->assertInertia(fn($page) => $page->has('reports', 0));
    $this->actingAs($this->farmerUser)->get("/disease-reports/{$report->uuid}/media/photo")->assertNotFound();
    expect(Notification::count())->toBe(0);
});

it('releases the report once the photo is stored: visible, routed and notified', function () {
    $uuid = hrWaiting();

    hrSend($uuid, 'photo', hrPhotoBytes());

    $report = hrReport();
    expect($report->status->value)->toBe('new')
        ->and($report->assigned_officer_id)->toBe($this->vet->id)
        ->and(DiseaseReport::count())->toBe(1);

    expect(Notification::where('user_id', $this->farmerUser->id)->where('kind', 'disease_report.submitted')->count())->toBe(1)
        ->and(Notification::where('user_id', $this->agent->id)->where('kind', 'disease_report.submitted')->count())->toBe(1)
        ->and(Notification::where('user_id', $this->vet->id)->where('kind', 'disease_report.submitted')->count())->toBe(1);

    $this->actingAs($this->vet)->get('/vet/dashboard')->assertInertia(fn($page) => $page->has('reports', 1));
});

it('sends a released report with no linked officer to the admin queue', function () {
    AgentOfficerAssignment::query()->delete();
    $uuid = hrWaiting();

    hrSend($uuid, 'photo', hrPhotoBytes());

    expect(hrReport()->assigned_officer_id)->toBeNull();
    $this->actingAs($this->admin)->get('/admin/disease-reports')->assertInertia(fn($page) => $page->has('reports', 1));
});

it('releases once however many times the photo is completed', function () {
    $uuid = hrWaiting();
    $bytes = hrPhotoBytes();

    hrSend($uuid, 'photo', $bytes);
    hrSend($uuid, 'photo', $bytes);
    hrSend($uuid, 'photo', $bytes);

    expect(Notification::where('kind', 'disease_report.submitted')->count())->toBe(3);
});

it('attaches a voice note that comes later without a second notification', function () {
    $uuid = hrWaiting();
    hrSend($uuid, 'photo', hrPhotoBytes());
    $notifications = Notification::count();

    hrSend($uuid, 'audio', hrVoiceBytes())->assertJson(['state' => 'complete']);

    $report = hrReport();
    Storage::disk('local')->assertExists($report->audio_path);
    expect($report->audio_path)->not->toBeNull()
        ->and(Notification::count())->toBe($notifications)
        ->and($report->status->value)->toBe('new');
});

it('keeps a voice note that comes first without releasing the report', function () {
    $uuid = hrWaiting();

    hrSend($uuid, 'audio', hrVoiceBytes());

    expect(hrReport()->audio_path)->not->toBeNull()
        ->and(hrReport()->status->value)->toBe('waiting_for_photo')
        ->and(Notification::count())->toBe(0)
        ->and(DiseaseReport::count())->toBe(0);
});

it('serves the stored media only to the farmer and the officer it was routed to', function () {
    $uuid = hrWaiting();
    hrSend($uuid, 'photo', hrPhotoBytes());
    $report = hrReport();
    $stranger = User::factory()->create();
    $stranger->assignRole('farmer');
    $otherVet = User::factory()->create();
    $otherVet->assignRole('vet');

    $this->actingAs($this->farmerUser)->get("/disease-reports/{$report->uuid}/media/photo")->assertOk();
    $this->actingAs($this->vet)->get("/disease-reports/{$report->uuid}/media/photo")->assertOk();
    $this->actingAs($stranger)->get("/disease-reports/{$report->uuid}/media/photo")->assertNotFound();
    $this->actingAs($otherVet)->get("/disease-reports/{$report->uuid}/media/photo")->assertNotFound();
    $this->actingAs($this->farmerUser)->get("/disease-reports/{$report->uuid}/media/audio")->assertNotFound();
});

it('shows a private photo to its officer through the media address, not a public one', function () {
    $uuid = hrWaiting();
    hrSend($uuid, 'photo', hrPhotoBytes());
    $report = hrReport();

    $this->actingAs($this->vet)->get("/vet/reports/{$report->uuid}")->assertInertia(
        fn($page) => $page->where('report.photo_url', fn($url) => str_contains($url, "/disease-reports/{$report->uuid}/media/photo") && ! str_contains($url, '/storage/')),
    );
});

it('limits how many uploads a farmer may hold open', function () {
    config(['health_reports.max_open_sessions' => 2]);
    $a = hrWaiting();
    $b = hrWaiting();
    $c = hrWaiting();

    hrOpen($a, 'photo', hrPhotoBytes())->assertSuccessful();
    hrOpen($b, 'photo', hrPhotoBytes())->assertSuccessful();
    hrOpen($c, 'photo', hrPhotoBytes())->assertStatus(429);

    // re-opening one already held is not a new session
    hrOpen($a, 'photo', hrPhotoBytes())->assertSuccessful();
});

it('limits how many requests a farmer may make', function () {
    config(['health_reports.requests_per_minute' => 3]);
    $uuid = hrWaiting();

    foreach (range(1, 3) as $_) {
        $this->actingAs($this->farmerUser)->getJson(hrUrl($uuid, 'photo'))->assertOk();
    }

    $this->actingAs($this->farmerUser)->getJson(hrUrl($uuid, 'photo'))->assertStatus(429);
});

it('treats an expired session as not there', function () {
    $uuid = hrWaiting();
    $bytes = hrPhotoBytes(800, 600);

    hrOpen($uuid, 'photo', $bytes)->assertSuccessful();
    hrChunk($uuid, 'photo', $bytes, 0)->assertOk();

    $this->travel(config('health_reports.session_hours') + 1)->hours();

    $this->actingAs($this->farmerUser)->getJson(hrUrl($uuid, 'photo'))->assertJson(['state' => 'none', 'offset' => 0]);
    hrChunk($uuid, 'photo', $bytes, 1024)->assertNotFound();
});

it('says both files are stored when the web form already sent the report', function () {
    $uuid = (string) Str::uuid();

    $this->actingAs($this->farmerUser)->post("/my-farm/{$this->unit->id}/report-problem", [
        'idempotency_key' => $uuid,
        'description' => 'Some birds look weak.',
        'photo' => UploadedFile::fake()->image('sick.jpg'),
    ])->assertRedirect();

    $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [[
        'type' => 'health_report', 'uuid' => $uuid, 'farmer' => $this->profile->uuid, 'farm_unit_id' => $this->unit->id,
        'description' => 'Some birds look weak.', 'event_date' => now()->toDateString(), 'device_created_at' => now()->toIso8601String(),
    ]]])->assertOk();

    $this->actingAs($this->farmerUser)->getJson(hrUrl($uuid, 'photo'))->assertJson(['state' => 'complete']);
    $this->actingAs($this->farmerUser)->getJson(hrUrl($uuid, 'audio'))->assertJson(['state' => 'complete']);
});
