<?php

// Only an admin adds institutions or corrects a lender's name, and every change is audit-logged. Nobody else can.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\AuditLog;
use App\Models\Lender;

beforeEach(function () {
    buildLoanWorld($this);

    $this->actors = ['admin' => $this->admin, 'farmer' => $this->farmerUser, 'agent' => $this->agent, 'vet' => $this->vet, 'adviser' => $this->adviser];
});

test('only an admin may open the lenders page, add an institution or correct a name', function (string $who, int $page, int $add, int $rename) {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Some Bank']);
    $user = $this->actors[$who];

    $this->actingAs($user)->get('/admin/lenders')->assertStatus($page);
    $this->actingAs($user)->postJson('/admin/lenders', ['type' => 'bank', 'name' => "New Bank {$who}"])->assertStatus($add);
    $this->actingAs($user)->patchJson("/admin/lenders/{$lender->uuid}", ['name' => 'Renamed Bank'])->assertStatus($rename);
})->with([
    'admin' => ['admin', 200, 201, 200],
    'farmer' => ['farmer', 403, 403, 403],
    'agent' => ['agent', 403, 403, 403],
    'vet' => ['vet', 403, 403, 403],
    'adviser' => ['adviser', 403, 403, 403],
]);

test('an admin adds an institution of any of the four types, it is audit-logged and searchable', function (string $type) {
    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => $type, 'name' => '  Zeta   Place '])->assertCreated()->assertJsonPath('name', 'Zeta Place');

    $lender = Lender::where('name', 'Zeta Place')->first();
    $log = AuditLog::where('auditable_type', Lender::class)->where('auditable_id', $lender->id)->first();

    expect($lender->type)->toBe($type)
        ->and($log->action)->toBe('lender.created')->and((int) $log->user_id)->toBe($this->admin->id)
        ->and($this->actingAs($this->farmerUser)->postJson('/lenders/search', ['q' => 'zeta'])->json())->toHaveCount(1);
})->with(['bank', 'microfinance', 'credit_union', 'other']);

test('a name must be new, ignoring case and extra spaces, and the type must be one of the four', function () {
    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'bank', 'name' => 'sika  MICROFINANCE'])->assertStatus(422);
    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'individual', 'name' => 'Some Person'])->assertStatus(422);
    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'group', 'name' => 'Some Group'])->assertStatus(422);
    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'bank', 'name' => '   '])->assertStatus(422);

    expect(Lender::count())->toBe(1);
});

test('an admin corrects a name, the change is audit-logged with before and after, and a clash is refused', function () {
    $bank = Lender::create(['type' => 'bank', 'name' => 'Old Name Bank']);

    $this->actingAs($this->admin)->patchJson("/admin/lenders/{$bank->uuid}", ['name' => 'New Name Bank'])->assertOk();

    $log = AuditLog::where('auditable_type', Lender::class)->where('auditable_id', $bank->id)->where('action', 'lender.updated')->first();

    expect($bank->fresh()->name)->toBe('New Name Bank')
        ->and($log->old_values['name'])->toBe('Old Name Bank')->and($log->new_values['name'])->toBe('New Name Bank');

    $this->actingAs($this->admin)->patchJson("/admin/lenders/{$this->institution->uuid}", ['name' => 'Sika Microfinance'])->assertOk();
    $this->actingAs($this->admin)->patchJson("/admin/lenders/{$this->institution->uuid}", ['name' => 'new  name  BANK'])->assertStatus(422);
});

test('the admin page lists type and name with no id of a row and no phone', function () {
    $props = null;
    $this->actingAs($this->admin)->get('/admin/lenders')->assertOk()->assertInertia(function ($page) use (&$props) {
        $props = $page->toArray()['props'];
    });

    expect(array_keys($props['lenders'][0]))->toEqualCanonicalizing(['uuid', 'type', 'name'])
        ->and($props['types'])->toBe(['bank', 'microfinance', 'credit_union', 'other']);
});
