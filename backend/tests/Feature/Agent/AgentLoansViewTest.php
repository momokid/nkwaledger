<?php

// The agent's Loans menu: the loans of the agent's own farmers, read-only, with the lender check and its note.
// Only the farmer's current agent. Nothing here links to the farmer's or the admin's pages, and no row id leaves the server.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Loan;
use App\Services\Loans\LoanService;

beforeEach(function () {
    buildLoanWorld($this);

    // the second farmer now belongs to another agent
    $this->clean->update(['assigned_agent_id' => $this->otherAgent->id]);

    $this->mine = ($this->openLoan)(['lender_name' => 'Sika Microfinance']);
    $this->theirs = app(LoanService::class)->record($this->clean, $this->clean->user, ($this->moneyEntry)(['lender_name' => 'Asante Rural Bank', 'lender_type' => 'rural_bank', 'amount' => '500', 'interest' => '50']));
    $this->listJson = fn(string $query = '') => $this->actingAs($this->agent)->getJson('/agent/loans' . $query)->assertOk()->json();
});

test('only an agent may open the list', function (string $who, int $status) {
    $user = ['agent' => $this->agent, 'admin' => $this->admin, 'farmer' => $this->farmerUser, 'vet' => $this->vet, 'adviser' => $this->adviser][$who];

    $this->actingAs($user)->get('/agent/loans')->assertStatus($status);
})->with([
    'agent' => ['agent', 200],
    'admin' => ['admin', 403],
    'farmer' => ['farmer', 403],
    'vet' => ['vet', 403],
    'adviser' => ['adviser', 403],
]);

test('the list holds only the loans of the agent\'s own farmers', function () {
    $mine = collect(($this->listJson)()['rows'])->pluck('lender')->all();
    $other = collect($this->actingAs($this->otherAgent)->getJson('/agent/loans')->json('rows'))->pluck('lender')->all();

    expect($mine)->toBe(['Sika Microfinance'])->and($other)->toBe(['Asante Rural Bank']);
});

test('the total owed counts the agent\'s own farmers only', function () {
    expect(($this->listJson)()['total_owed'])->toBe(120000);
});

test('the status filter and the search stay inside the agent\'s own farmers', function () {
    $waitingMine = $this->entries->recordLoan($this->profile, $this->agent, ($this->moneyEntry)(['lender_name' => 'Waiting Bank']));
    $this->entries->recordLoan($this->clean, $this->otherAgent, ($this->moneyEntry)(['lender_name' => 'Their Waiting Bank']));

    $names = fn(string $q) => collect(($this->listJson)($q)['rows'])->pluck('lender')->all();

    expect($names('?status=waiting'))->toBe(['Waiting Bank'])
        ->and($names('?search=rural'))->toBe([])
        ->and($names('?status=active'))->toBe(['Sika Microfinance'])
        ->and(collect(($this->listJson)('?status=waiting')['rows'])->first()['uuid'])->toBe($waitingMine->uuid);
});

test('the page opens with the first page in it, and no row id', function () {
    $props = $this->actingAs($this->agent)->get('/agent/loans')->viewData('page')['props'];

    expect($props['rows'])->toHaveCount(1)->and($props['total_owed'])->toBe(120000)
        ->and($props['filters'])->toBe(['search' => '', 'status' => 'all'])
        ->and(json_encode([$props['rows'], $props['pagination']]))->not->toContain('"id"')->not->toContain('farmer_profile_id');
});

test('an agent opens the loan of their own farmer and sees the repayments, the check and its history', function () {
    ($this->repay)($this->mine, '600');
    $this->actingAs($this->admin)->postJson("/admin/loans/{$this->mine->uuid}/checks", ['status' => 'confirmed', 'note' => 'Spoke to the manager'])->assertStatus(201);

    $props = $this->actingAs($this->agent)->get("/agent/loans/{$this->mine->uuid}")->assertOk()->viewData('page')['props'];

    expect($props['loan']['lender'])->toBe('Sika Microfinance')->and($props['loan']['balance'])->toBe(60000)
        ->and($props['loan']['check']['note'])->toBe('Spoke to the manager')
        ->and($props['repayments'])->toHaveCount(1)
        ->and($props['checks'])->toHaveCount(1)
        ->and(array_keys($props))->not->toContain('postUrl')->not->toContain('previewUrl')->not->toContain('paidFrom')->not->toContain('idempotencyKey');
});

test('a loan of another agent\'s farmer does not exist for this agent', function () {
    $this->actingAs($this->agent)->get("/agent/loans/{$this->theirs->uuid}")->assertNotFound();
});

test('nobody but an agent opens a loan here', function (string $who, int $status) {
    $user = ['admin' => $this->admin, 'farmer' => $this->farmerUser, 'vet' => $this->vet, 'adviser' => $this->adviser][$who];

    $this->actingAs($user)->get("/agent/loans/{$this->mine->uuid}")->assertStatus($status);
})->with([
    'admin' => ['admin', 403],
    'farmer' => ['farmer', 403],
    'vet' => ['vet', 403],
    'adviser' => ['adviser', 403],
]);

test('an agent cannot save a check: that stays with the admin', function () {
    $this->actingAs($this->agent)->postJson("/admin/loans/{$this->mine->uuid}/checks", ['status' => 'confirmed'])->assertForbidden();
    $this->actingAs($this->agent)->postJson("/agent/loans/{$this->mine->uuid}/checks", ['status' => 'confirmed'])->assertStatus(404);
});

test('nothing on the agent\'s loan pages sends the agent into a farmer\'s or the admin\'s pages', function () {
    // the page's own props only: the shared ones (menus, account) are not part of this page
    $own = fn(array $props, array $keys) => json_encode(array_intersect_key($props, array_flip($keys)));
    $list = $own($this->actingAs($this->agent)->get('/agent/loans')->viewData('page')['props'], ['rows', 'pagination', 'total_owed', 'filters']);
    $show = $own($this->actingAs($this->agent)->get("/agent/loans/{$this->mine->uuid}")->viewData('page')['props'], ['loan', 'farmer', 'repayments', 'rejected', 'checks']);

    foreach ([$list, $show] as $json) {
        expect($json)->not->toContain('\/admin')->not->toContain('\/my-farm')->not->toContain('\/loans\/')->not->toContain('"id"');
    }
});

test('a cancelled loan is listed as cancelled and opens like any other', function () {
    $gone = ($this->openLoan)(['lender_name' => 'Gone Bank']);
    Loan::whereKey($gone->id)->update(['status' => Loan::CANCELLED]);

    $row = collect(($this->listJson)('?status=cancelled')['rows'])->first();

    expect($row['lender'])->toBe('Gone Bank')->and($row['status'])->toBe('cancelled');
    $this->actingAs($this->agent)->get("/agent/loans/{$gone->uuid}")->assertOk();
});
