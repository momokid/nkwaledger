<?php

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo([
        'accounting-periods.view',
        'accounting-periods.create',
        'accounting-periods.close',
        'accounting-periods.reopen',
    ]);
});

function periodPayload(array $overrides = []): array
{
    return array_merge([
        'name'      => 'January 2026',
        'starts_on' => '2026-01-01',
        'ends_on'   => '2026-01-31',
    ], $overrides);
}

test('a guest is redirected to login', function () {
    $this->get('/admin/accounting-periods')->assertRedirect('/login');
});

test('a user without the view permission is forbidden', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');
    $agent->revokePermissionTo('accounting-periods.view');

    $this->actingAs($agent)->get('/admin/accounting-periods')->assertForbidden();
});

test('an authorized admin sees the page', function () {
    $this->actingAs($this->admin)->get('/admin/accounting-periods')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/AccountingPeriods/Index'));
});

test('periods are listed newest first', function () {
    AccountingPeriod::factory()->create(['name' => 'January 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-01-31']);
    AccountingPeriod::factory()->create(['name' => 'February 2026', 'starts_on' => '2026-02-01', 'ends_on' => '2026-02-28']);

    $this->actingAs($this->admin)->get('/admin/accounting-periods')
        ->assertInertia(fn($page) => $page->where('periods.data.0.name', 'February 2026'));
});

test('a period can be created', function () {
    $this->actingAs($this->admin)->post('/admin/accounting-periods', periodPayload())
        ->assertSessionDoesntHaveErrors();

    expect(AccountingPeriod::where('name', 'January 2026')->exists())->toBeTrue();
});

test('creating needs the create permission', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('agent');

    $this->actingAs($viewer)->post('/admin/accounting-periods', periodPayload())->assertForbidden();
});

test('a name is required', function () {
    $this->actingAs($this->admin)->post('/admin/accounting-periods', periodPayload(['name' => '']))
        ->assertSessionHasErrors('name');
});

test('a duplicate name is refused', function () {
    AccountingPeriod::factory()->create(['name' => 'January 2026']);

    $this->actingAs($this->admin)->post('/admin/accounting-periods', periodPayload())
        ->assertSessionHasErrors('name');
});

test('an end date before the start is refused', function () {
    $this->actingAs($this->admin)->post('/admin/accounting-periods', periodPayload([
        'ends_on' => '2025-12-01',
    ]))->assertSessionHasErrors('ends_on');
});

// a transaction dated in the overlap would have two possible homes
test('an overlapping period is refused with a clear message', function () {
    AccountingPeriod::factory()->create([
        'name'      => 'January 2026',
        'starts_on' => '2026-01-01',
        'ends_on'   => '2026-01-31',
    ]);

    $this->actingAs($this->admin)->post('/admin/accounting-periods', periodPayload([
        'name'      => 'Mid January',
        'starts_on' => '2026-01-15',
        'ends_on'   => '2026-02-15',
    ]))->assertSessionHasErrors('starts_on');
});

test('a period can be closed', function () {
    $period = AccountingPeriod::factory()->create();

    $this->actingAs($this->admin)->patch("/admin/accounting-periods/{$period->id}/close")
        ->assertSessionDoesntHaveErrors();

    expect($period->fresh()->status)->toBe('closed');
    expect($period->fresh()->closed_by)->toBe($this->admin->id);
});

test('closing needs the close permission', function () {
    $period = AccountingPeriod::factory()->create();

    $viewer = User::factory()->create();
    $viewer->assignRole('agent');

    $this->actingAs($viewer)->patch("/admin/accounting-periods/{$period->id}/close")->assertForbidden();
});

test('closing is recorded', function () {
    $period = AccountingPeriod::factory()->create();

    $this->actingAs($this->admin)->patch("/admin/accounting-periods/{$period->id}/close");

    expect(AuditLog::where('action', 'period.closed')->exists())->toBeTrue();
});

test('a closed period can be reopened', function () {
    $period = AccountingPeriod::factory()->closed()->create();

    $this->actingAs($this->admin)->patch("/admin/accounting-periods/{$period->id}/reopen")
        ->assertSessionDoesntHaveErrors();

    expect($period->fresh()->status)->toBe('open');
});

// closing is routine, reopening is not, so they are separate privileges
test('reopening needs its own permission', function () {
    $period = AccountingPeriod::factory()->closed()->create();

    $closer = User::factory()->create();
    $closer->assignRole('agent');
    $closer->givePermissionTo(['accounting-periods.view', 'accounting-periods.close']);

    $this->actingAs($closer)->patch("/admin/accounting-periods/{$period->id}/reopen")->assertForbidden();
});

test('reopening is recorded', function () {
    $period = AccountingPeriod::factory()->closed()->create();

    $this->actingAs($this->admin)->patch("/admin/accounting-periods/{$period->id}/reopen");

    expect(AuditLog::where('action', 'period.reopened')->exists())->toBeTrue();
});

test('closing an already closed period is refused', function () {
    $period = AccountingPeriod::factory()->closed()->create();

    $this->actingAs($this->admin)->patch("/admin/accounting-periods/{$period->id}/close")
        ->assertSessionHasErrors();
});

test('the page says what this admin may do', function () {
    $this->actingAs($this->admin)->get('/admin/accounting-periods')
        ->assertInertia(
            fn($page) => $page
                ->where('permissions.create', true)
                ->where('permissions.close', true)
                ->where('permissions.reopen', true)
        );
});
