<?php

use App\Enums\KioskReportStatus;
use App\Models\KioskReport;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
});

test('a guest is redirected to login when visiting the kiosk reports list', function () {
    $this->get('/admin/marketplace/kiosk-reports')->assertRedirect('/login');
});

test('a user without marketplace-kiosks.view cannot view the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/marketplace/kiosk-reports')->assertForbidden();
});

test('a user with marketplace-kiosks.view can view the list, with the reporter\'s name', function () {
    $report = KioskReport::factory()->create();

    $user = User::factory()->create();
    $user->givePermissionTo('marketplace-kiosks.view');

    $this->actingAs($user)->get('/admin/marketplace/kiosk-reports')
        ->assertInertia(fn($page) => $page
            ->component('Admin/Marketplace/KioskReports/Index')
            ->where('reports.data.0.uuid', $report->uuid)
            ->where(
                'reports.data.0.reporter',
                trim("{$report->farmerProfile->user->surname} {$report->farmerProfile->user->first_name}"),
            ));
});

test('the list can be filtered down to a single status', function () {
    KioskReport::factory()->create();
    $withAdmin = KioskReport::factory()->withAdmin()->create();

    $user = User::factory()->create();
    $user->givePermissionTo('marketplace-kiosks.view');

    $this->actingAs($user)->get('/admin/marketplace/kiosk-reports?status=with_admin')
        ->assertInertia(fn($page) => $page
            ->where('reports.data', fn($rows) => count($rows) === 1)
            ->where('reports.data.0.uuid', $withAdmin->uuid));
});

test('resolving a report marks it resolved and records who resolved it', function () {
    $report = KioskReport::factory()->withAdmin()->create();

    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-kiosks.suspend');

    $this->actingAs($admin)->post("/admin/marketplace/kiosk-reports/{$report->uuid}/resolve")
        ->assertSessionHasNoErrors();

    $report->refresh();

    expect($report->status)->toBe(KioskReportStatus::Resolved)
        ->and($report->resolved_by)->toBe($admin->id)
        ->and($report->resolved_at)->not->toBeNull();
});

test('resolving a report needs the suspend permission', function () {
    $report = KioskReport::factory()->withAdmin()->create();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('marketplace-kiosks.view');

    $this->actingAs($viewer)->post("/admin/marketplace/kiosk-reports/{$report->uuid}/resolve")->assertForbidden();
});

test('extending a with-admin report pushes admin_due_at out', function () {
    $report = KioskReport::factory()->withAdmin()->create(['admin_due_at' => now()->addDay()]);
    $originalDueAt = $report->admin_due_at;

    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-kiosks.suspend');

    $this->actingAs($admin)->post("/admin/marketplace/kiosk-reports/{$report->uuid}/extend")
        ->assertSessionHasNoErrors();

    expect($report->fresh()->admin_due_at->greaterThan($originalDueAt))->toBeTrue();
});

test('extending needs the suspend permission', function () {
    $report = KioskReport::factory()->withAdmin()->create();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('marketplace-kiosks.view');

    $this->actingAs($viewer)->post("/admin/marketplace/kiosk-reports/{$report->uuid}/extend")->assertForbidden();
});
