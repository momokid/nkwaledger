<?php

use App\Enums\DiseaseReportStatus;
use App\Models\AuditLog;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\HealthReportUpload;
use App\Models\SyncSubmission;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Storage::fake('local');
    config(['filesystems.photo_disk' => 'local']);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
    $this->unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function purgeReport(string $status, int $daysOld, bool $withFiles = true): DiseaseReport
{
    $report = DiseaseReport::factory()->create([
        'farm_unit_id' => test()->unit->id,
        'farmer_profile_id' => test()->profile->id,
        'status' => $status,
        'photo_path' => null,
        'media_disk' => 'local',
        'created_at' => now()->subDays($daysOld),
        'updated_at' => now()->subDays($daysOld),
    ]);

    if ($withFiles) {
        Storage::disk('local')->put("disease-reports/{$report->uuid}.webm", 'voice');
        Storage::disk('local')->put("health-report-uploads/{$report->uuid}.part", 'partial');
        $report->forceFill(['audio_path' => "disease-reports/{$report->uuid}.webm"])->save();
        HealthReportUpload::create([
            'uuid' => (string) Str::uuid(),
            'disease_report_id' => $report->id,
            'user_id' => test()->farmerUser->id,
            'kind' => 'photo',
            'total_bytes' => 100,
            'sha256' => str_repeat('a', 64),
            'part_path' => "health-report-uploads/{$report->uuid}.part",
            'expires_at' => now()->addDay(),
        ]);
    }

    SyncSubmission::create([
        'client_uuid' => (string) Str::uuid(), 'type' => 'health_report', 'user_id' => test()->farmerUser->id,
        'farmer_profile_id' => test()->profile->id, 'payload' => [], 'device_date' => now(), 'received_at' => now(),
        'status' => 'accepted', 'disease_report_id' => $report->id,
    ]);

    return $report;
}

it('waits 30 days before removing a waiting report', function () {
    expect(config('health_reports.waiting_max_age_days'))->toBe(30);
});

it('removes waiting reports older than the limit with their files, and nothing else', function () {
    $old = purgeReport('waiting_for_photo', 31);
    $young = purgeReport('waiting_for_photo', 29);
    $released = purgeReport('new', 90, false);
    $released->forceFill(['photo_path' => 'disease-reports/kept.webp'])->save();
    Storage::disk('local')->put('disease-reports/kept.webp', 'photo');

    $this->artisan('health-reports:purge-waiting')->assertSuccessful();

    expect(DiseaseReport::withoutGlobalScopes()->whereKey($old->id)->exists())->toBeFalse()
        ->and(DiseaseReport::withoutGlobalScopes()->whereKey($young->id)->exists())->toBeTrue()
        ->and(DiseaseReport::withoutGlobalScopes()->whereKey($released->id)->exists())->toBeTrue();

    Storage::disk('local')->assertMissing("disease-reports/{$old->uuid}.webm");
    Storage::disk('local')->assertMissing("health-report-uploads/{$old->uuid}.part");
    Storage::disk('local')->assertExists("disease-reports/{$young->uuid}.webm");
    Storage::disk('local')->assertExists("health-report-uploads/{$young->uuid}.part");
    Storage::disk('local')->assertExists('disease-reports/kept.webp');
    expect(HealthReportUpload::where('disease_report_id', $old->id)->count())->toBe(0)
        ->and(HealthReportUpload::where('disease_report_id', $young->id)->count())->toBe(1);
});

it('leaves the phone\'s record of a removed report pointing at nothing, so the phone sees it is gone', function () {
    $old = purgeReport('waiting_for_photo', 31);
    $submission = SyncSubmission::where('type', 'health_report')->firstOrFail();

    $this->artisan('health-reports:purge-waiting')->assertSuccessful();

    expect($submission->fresh()->disease_report_id)->toBeNull();
    $this->actingAs($this->farmerUser)->getJson("/sync/health-reports/{$submission->client_uuid}/photo")->assertNotFound();
});

it('writes an audit entry with the count', function () {
    purgeReport('waiting_for_photo', 40);
    purgeReport('waiting_for_photo', 50, false);
    purgeReport('waiting_for_photo', 1);

    $this->artisan('health-reports:purge-waiting')->assertSuccessful();

    $entry = AuditLog::where('action', 'health_report.waiting_purged')->firstOrFail();
    expect($entry->new_values['count'])->toBe(2)->and($entry->new_values['older_than_days'])->toBe(30);
});

it('writes an audit entry of zero when there is nothing to remove', function () {
    $this->artisan('health-reports:purge-waiting')->assertSuccessful();

    expect(AuditLog::where('action', 'health_report.waiting_purged')->firstOrFail()->new_values['count'])->toBe(0);
});

it('also removes upload sessions that have expired, with their partial files', function () {
    $young = purgeReport('waiting_for_photo', 1);
    $session = HealthReportUpload::firstOrFail();
    $session->forceFill(['expires_at' => now()->subHour()])->save();

    $this->artisan('health-reports:purge-waiting')->assertSuccessful();

    expect(HealthReportUpload::count())->toBe(0);
    Storage::disk('local')->assertMissing("health-report-uploads/{$young->uuid}.part");
    expect(DiseaseReportStatus::from($young->fresh()->status->value))->toBe(DiseaseReportStatus::WaitingForPhoto);
});

it('runs every day', function () {
    $event = collect(app(Schedule::class)->events())->first(fn($e) => str_contains($e->command, 'health-reports:purge-waiting'));

    expect($event)->not->toBeNull()->and($event->expression)->toMatch('/^\d+ \d+ \* \* \*$/');
});

it('gives the admin the current count of waiting reports', function () {
    purgeReport('waiting_for_photo', 2, false);
    purgeReport('waiting_for_photo', 3, false);
    purgeReport('new', 3, false);

    $this->actingAs($this->admin)->get('/admin/disease-reports')
        ->assertOk()->assertInertia(fn($page) => $page->where('waitingCount', 2));
});
