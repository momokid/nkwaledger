<?php

use App\Enums\KioskReportStatus;
use App\Mail\KioskReportedMail;
use App\Models\FarmerProfile;
use App\Models\Kiosk;
use App\Models\KioskReport;
use App\Models\Notification;
use App\Models\Supplier;
use App\Models\User;
use App\Services\KioskReportService;
use App\Services\SettingsService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    Mail::fake();
    $this->service = app(KioskReportService::class);
});

function kioskForReport(): Kiosk
{
    $user = User::factory()->create();
    $user->assignRole('supplier');
    $supplier = Supplier::factory()->verified()->create(['user_id' => $user->id]);

    return Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
}

function reportingFarmer(): FarmerProfile
{
    return FarmerProfile::factory()->create();
}

test('a farmer report emails the supplier and alerts admin', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-kiosks.suspend');

    $kiosk = kioskForReport();
    $farmer = reportingFarmer();

    $report = $this->service->submit($farmer, $kiosk, 'wrong_price', 'The price shown is not what they charge.');

    expect($report->status)->toBe(KioskReportStatus::Open)
        ->and($report->supplier_due_at)->not->toBeNull();

    Mail::assertSent(KioskReportedMail::class);

    expect(Notification::where('user_id', $kiosk->supplier->user_id)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $admin->id)->exists())->toBeTrue();
});

test('a farmer cannot report the same kiosk twice', function () {
    $kiosk = kioskForReport();
    $farmer = reportingFarmer();

    $this->service->submit($farmer, $kiosk, 'wrong_price');

    expect(fn() => $this->service->submit($farmer, $kiosk, 'no_stock'))
        ->toThrow(InvalidArgumentException::class, 'You have already reported this kiosk.');

    expect(KioskReport::where('kiosk_id', $kiosk->id)->where('farmer_profile_id', $farmer->id)->count())->toBe(1);
});

test('two different farmers can each report the same kiosk once', function () {
    $kiosk = kioskForReport();

    $this->service->submit(reportingFarmer(), $kiosk, 'wrong_price');
    $this->service->submit(reportingFarmer(), $kiosk, 'no_stock');

    expect(KioskReport::where('kiosk_id', $kiosk->id)->count())->toBe(2);
});

test('the supplier response due date comes from the settings service, not a hard-coded number', function () {
    $admin = User::factory()->create();
    app(SettingsService::class)->set('marketplace.supplier_report_response_days', '7', $admin);

    $report = $this->service->submit(reportingFarmer(), kioskForReport(), 'wrong_price');

    expect(round(now()->diffInDays($report->supplier_due_at)))->toEqualWithDelta(7, 1);
});

test('a supplier answering moves the report to supplier_answered, not with_admin', function () {
    $report = KioskReport::factory()->create(['kiosk_id' => kioskForReport()->id]);

    $this->service->answer($report, 'We have restocked already.');

    expect($report->fresh()->status)->toBe(KioskReportStatus::SupplierAnswered)
        ->and($report->fresh()->supplier_answer)->toBe('We have restocked already.')
        ->and($report->fresh()->supplier_answered_at)->not->toBeNull();
});

test('answering a report that is no longer open is refused', function () {
    $report = KioskReport::factory()->withAdmin()->create(['kiosk_id' => kioskForReport()->id]);

    expect(fn() => $this->service->answer($report, 'Late answer'))
        ->toThrow(InvalidArgumentException::class);
});

test('a report still silent past its supplier due date moves to admin via the scheduled job', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-kiosks.suspend');

    $report = KioskReport::factory()->create([
        'kiosk_id' => kioskForReport()->id,
        'supplier_due_at' => now()->subDay(),
    ]);

    $moved = $this->service->escalateSilent();

    expect($moved)->toBe(1)
        ->and($report->fresh()->status)->toBe(KioskReportStatus::WithAdmin)
        ->and($report->fresh()->admin_due_at)->not->toBeNull();

    expect(Notification::where('user_id', $admin->id)->where('kind', 'marketplace.report_unanswered')->exists())->toBeTrue();
});

test('a report still within its supplier window is not escalated', function () {
    KioskReport::factory()->create([
        'kiosk_id' => kioskForReport()->id,
        'supplier_due_at' => now()->addDay(),
    ]);

    expect($this->service->escalateSilent())->toBe(0);
});

test('admin can resolve a report at any status', function () {
    $admin = User::factory()->create();
    $report = KioskReport::factory()->withAdmin()->create(['kiosk_id' => kioskForReport()->id]);

    $this->service->resolve($report, $admin);

    expect($report->fresh()->status)->toBe(KioskReportStatus::Resolved)
        ->and($report->fresh()->resolved_by)->toBe($admin->id)
        ->and($report->fresh()->resolved_at)->not->toBeNull();
});

test('admin resolving an open report (before any supplier answer) also works', function () {
    $admin = User::factory()->create();
    $report = KioskReport::factory()->create(['kiosk_id' => kioskForReport()->id]);

    $this->service->resolve($report, $admin);

    expect($report->fresh()->status)->toBe(KioskReportStatus::Resolved);
});

test('the admin-window-ended job only alerts admin - it never suspends anything', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-kiosks.suspend');

    $kiosk = kioskForReport();
    $report = KioskReport::factory()->withAdmin()->create([
        'kiosk_id' => $kiosk->id,
        'admin_due_at' => now()->subDay(),
    ]);

    $alerted = $this->service->alertOverdue();

    expect($alerted)->toBe(1)
        ->and($report->fresh()->status)->toBe(KioskReportStatus::WithAdmin) // unchanged - not Suspended
        ->and($kiosk->fresh()->status->value)->not->toBe('suspended')
        ->and($report->fresh()->admin_window_alerted_at)->not->toBeNull();

    expect(Notification::where('user_id', $admin->id)->where('kind', 'marketplace.report_window_ended')->exists())->toBeTrue();
});

test('the admin-window-ended alert fires once, not every day', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-kiosks.suspend');

    KioskReport::factory()->withAdmin()->create([
        'kiosk_id' => kioskForReport()->id,
        'admin_due_at' => now()->subDay(),
        'admin_window_alerted_at' => now()->subHour(),
    ]);

    expect($this->service->alertOverdue())->toBe(0);
});

test('extending the admin window pushes the due date out again and clears the alert flag', function () {
    $report = KioskReport::factory()->withAdmin()->create([
        'kiosk_id' => kioskForReport()->id,
        'admin_due_at' => now()->subDay(),
        'admin_window_alerted_at' => now(),
    ]);

    $this->service->extendAdminWindow($report);

    expect($report->fresh()->admin_due_at->isFuture())->toBeTrue()
        ->and($report->fresh()->admin_window_alerted_at)->toBeNull()
        ->and($report->fresh()->status)->toBe(KioskReportStatus::WithAdmin);
});

test('extending a report not currently with admin is refused', function () {
    $report = KioskReport::factory()->create(['kiosk_id' => kioskForReport()->id]);

    expect(fn() => $this->service->extendAdminWindow($report))->toThrow(InvalidArgumentException::class);
});

test('manually suspending marks any open report on that kiosk as suspended', function () {
    $kiosk = kioskForReport();
    $report = KioskReport::factory()->withAdmin()->create(['kiosk_id' => $kiosk->id]);

    $this->service->markSuspended($report);

    expect($report->fresh()->status)->toBe(KioskReportStatus::Suspended);
});

test('marking an already-resolved report as suspended does nothing', function () {
    $admin = User::factory()->create();
    $report = KioskReport::factory()->create(['kiosk_id' => kioskForReport()->id]);
    $this->service->resolve($report, $admin);

    $this->service->markSuspended($report);

    expect($report->fresh()->status)->toBe(KioskReportStatus::Resolved);
});

test('several different farmers reporting the same kiosk raises the urgent flag', function () {
    $kiosk = kioskForReport();

    $this->service->submit(reportingFarmer(), $kiosk, 'wrong_price');
    expect($kiosk->fresh()->hasUrgentReports())->toBeFalse();

    $this->service->submit(reportingFarmer(), $kiosk, 'no_stock');
    expect($kiosk->fresh()->hasUrgentReports())->toBeTrue();
});

test('the same farmer reporting twice does not raise the urgent flag (blocked entirely, not double-counted)', function () {
    $kiosk = kioskForReport();
    $farmer = reportingFarmer();

    $this->service->submit($farmer, $kiosk, 'wrong_price');

    expect($kiosk->fresh()->hasUrgentReports())->toBeFalse();
});

test('suspending a supplier through the existing admin action also marks their open reports suspended', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('marketplace-suppliers.suspend');

    $kiosk = kioskForReport();
    $report = KioskReport::factory()->create(['kiosk_id' => $kiosk->id]);

    $this->actingAs($admin)->patch(route('admin.marketplace.suppliers.suspend', $kiosk->supplier), [
        'reason' => 'Unresolved report',
    ])->assertRedirect();

    expect($report->fresh()->status)->toBe(KioskReportStatus::Suspended)
        ->and($kiosk->fresh()->status->value)->toBe('suspended');
});
