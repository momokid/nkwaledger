<?php

// Task 3.9: a farmer, or an agent for a farmer, asks for a lender that is not in the list. The admin keeps a queue
// of the waiting requests and adds the lender, uses one already in the list, or rejects with a reason. The person
// who asked is told. Every step is audit-logged and nothing here shows a row id.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\AuditLog;
use App\Models\Lender;
use App\Models\LenderRequest;
use App\Models\Notification;
use App\Services\NavigationAccessService;

beforeEach(function () {
    buildLoanWorld($this);

    $this->ask = fn($user, array $body = [], ?string $farmer = null) => $this->actingAs($user)->postJson('/lender-requests', array_filter(array_merge([
        'name' => 'Nsawam Community Bank', 'type' => 'rural_bank', 'note' => 'Near the market', 'farmer' => $farmer,
    ], $body), fn($value) => $value !== null));
    $this->queue = fn(string $query = '') => $this->actingAs($this->admin)->getJson('/admin/lender-requests' . $query)->assertOk()->json();
    $this->decide = fn(string $uuid, string $action, array $body = []) => $this->actingAs($this->admin)->postJson("/admin/lender-requests/{$uuid}/{$action}", $body);
    $this->told = fn($user) => Notification::where('user_id', $user->id)->where('kind', 'loan.lender_request')->latest('id')->first();
});

// --- asking ---

test('a farmer asks for a lender for themselves', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201)->assertJson(['ok' => true]);

    $request = LenderRequest::firstOrFail();

    expect([$request->status, $request->name, $request->type, $request->note])->toBe(['pending', 'Nsawam Community Bank', 'rural_bank', 'Near the market'])
        ->and((int) $request->requested_by)->toBe($this->farmerUser->id)
        ->and((int) $request->farmer_profile_id)->toBe($this->profile->id)
        ->and($request->uuid)->not->toBeEmpty()
        ->and(AuditLog::where('action', 'lender_request.created')->count())->toBe(1);
});

test('a farmer cannot ask on behalf of another farmer', function () {
    ($this->ask)($this->farmerUser, [], $this->clean->uuid)->assertStatus(201);

    expect((int) LenderRequest::first()->farmer_profile_id)->toBe($this->profile->id);
});

test('an agent asks for a lender for their own farmer', function () {
    ($this->ask)($this->agent, [], $this->profile->uuid)->assertStatus(201);

    $request = LenderRequest::firstOrFail();

    expect((int) $request->requested_by)->toBe($this->agent->id)->and((int) $request->farmer_profile_id)->toBe($this->profile->id);
});

test('an agent must name a farmer, and only one of their own', function () {
    ($this->ask)($this->agent)->assertStatus(422);
    ($this->ask)($this->otherAgent, [], $this->profile->uuid)->assertNotFound();
    ($this->ask)($this->agent, [], 'not-a-uuid')->assertStatus(422);

    expect(LenderRequest::count())->toBe(0);
});

test('only a farmer or an agent may ask', function (string $who) {
    $user = ['admin' => $this->admin, 'vet' => $this->vet, 'adviser' => $this->adviser][$who];

    ($this->ask)($user, [], $this->profile->uuid)->assertNotFound();
})->with(['admin', 'vet', 'adviser']);

test('a request is checked like an admin adding a lender, and the note is optional', function () {
    ($this->ask)($this->farmerUser, ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
    ($this->ask)($this->farmerUser, ['type' => 'shop'])->assertStatus(422)->assertJsonValidationErrors('type');
    ($this->ask)($this->farmerUser, ['name' => 'Bank ɔ'])->assertStatus(422)->assertJsonPath('errors.name.0', 'Use only letters, numbers and common symbols in the Lender\'s name');
    ($this->ask)($this->farmerUser, ['note' => str_repeat('x', 501)])->assertStatus(422)->assertJsonValidationErrors('note');
    ($this->ask)($this->farmerUser, ['note' => null])->assertStatus(201);

    expect(LenderRequest::count())->toBe(1);
});

test('a lender already in the list cannot be requested, however it is spelt', function () {
    ($this->ask)($this->farmerUser, ['name' => 'sika   MICROFINANCE'])->assertStatus(422)->assertJsonPath('errors.name.0', 'Lender already exists');

    expect(LenderRequest::count())->toBe(0);
});

test('a name still waiting cannot be requested again, by anyone', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201);
    ($this->ask)($this->agent, ['name' => 'nsawam community  bank'], $this->profile->uuid)->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'This lender has already been contacted waiting for response');

    expect(LenderRequest::count())->toBe(1);
});

test('a name can be asked for again once its earlier request was answered', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201);
    ($this->decide)(LenderRequest::first()->uuid, 'reject', ['reason' => 'Not a real bank'])->assertOk();

    ($this->ask)($this->farmerUser)->assertStatus(201);

    expect(LenderRequest::count())->toBe(2);
});

// --- the admin's queue ---

test('only an admin opens the queue', function (string $who, int $status) {
    $user = ['admin' => $this->admin, 'farmer' => $this->farmerUser, 'agent' => $this->agent, 'vet' => $this->vet, 'adviser' => $this->adviser][$who];

    $this->actingAs($user)->get('/admin/lender-requests')->assertStatus($status);
})->with([
    'admin' => ['admin', 200],
    'farmer' => ['farmer', 403],
    'agent' => ['agent', 403],
    'vet' => ['vet', 403],
    'adviser' => ['adviser', 403],
]);

test('the queue lists only the waiting requests, oldest first, with who asked, and no row id', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201);
    ($this->ask)($this->agent, ['name' => 'Tamale Savings', 'type' => 'microfinance', 'note' => null], $this->profile->uuid)->assertStatus(201);
    ($this->ask)($this->farmerUser, ['name' => 'Already Gone'])->assertStatus(201);
    ($this->decide)(LenderRequest::where('name', 'Already Gone')->first()->uuid, 'reject', ['reason' => 'No'])->assertOk();

    $rows = ($this->queue)()['rows'];
    $agentName = trim("{$this->agent->surname} {$this->agent->first_name}");

    expect(collect($rows)->pluck('name')->all())->toBe(['Nsawam Community Bank', 'Tamale Savings'])
        ->and($rows[0]['requested_by'])->toBe(trim("{$this->farmerUser->surname} {$this->farmerUser->first_name}"))
        ->and($rows[1]['requested_by'])->toBe($agentName)
        ->and($rows[0])->toHaveKeys(['uuid', 'name', 'type', 'note', 'date'])
        ->and(json_encode($rows))->not->toContain('"id"')->not->toContain('farmer_profile_id');
});

test('the page opens with the first rows already in it', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201);

    $props = $this->actingAs($this->admin)->get('/admin/lender-requests')->viewData('page')['props'];

    expect($props['rows'])->toHaveCount(1)->and($props['types'])->toBe(Lender::TYPES);
});

test('the admin menu lists Lender Requests after Lenders', function () {
    $credit = collect(app(NavigationAccessService::class)->adminMenu($this->admin))->firstWhere('label', 'Credit');

    expect(collect($credit['children'])->pluck('label')->all())->toBe(['Loans', 'Loan Analysis', 'Lenders', 'Lender Requests']);
});

// --- answering ---

test('adding makes the lender, closes the request and tells the person who asked', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201);
    $request = LenderRequest::first();

    ($this->decide)($request->uuid, 'add')->assertOk();

    $lender = Lender::where('name', 'Nsawam Community Bank')->firstOrFail();
    $note = ($this->told)($this->farmerUser);

    expect($lender->type)->toBe('rural_bank')
        ->and($request->fresh()->status)->toBe('added')
        ->and((int) $request->fresh()->lender_id)->toBe($lender->id)
        ->and((int) $request->fresh()->decided_by)->toBe($this->admin->id)
        ->and($note->message)->toBe('Nsawam Community Bank has been added. You can now select it')
        ->and($note->link)->toBe('/loans')
        ->and(AuditLog::where('action', 'lender_request.added')->count())->toBe(1)
        ->and(AuditLog::where('action', 'lender.created')->count())->toBe(1)
        ->and(($this->queue)()['rows'])->toBe([]);
});

test('an agent\'s request is answered to the agent only, and they are sent to their own page for that farmer', function () {
    ($this->ask)($this->agent, [], $this->profile->uuid)->assertStatus(201);

    ($this->decide)(LenderRequest::first()->uuid, 'add')->assertOk();

    expect(($this->told)($this->agent)->link)->toBe("/agent/farmers/{$this->profile->uuid}")
        ->and(($this->told)($this->farmerUser))->toBeNull();
});

test('adding fails, and changes nothing, when the lender has been added another way meanwhile', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201);
    app(\App\Services\Loans\LenderService::class)->addInstitution('bank', 'Nsawam Community Bank', $this->admin);

    ($this->decide)(LenderRequest::first()->uuid, 'add')->assertStatus(422)->assertJsonPath('errors.name.0', 'Lender already exists');

    expect(LenderRequest::first()->status)->toBe('pending')->and(Lender::where('name', 'Nsawam Community Bank')->count())->toBe(1);
});

test('using an existing lender closes the request with that lender and says so', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201);
    $request = LenderRequest::first();

    ($this->decide)($request->uuid, 'use-existing', ['lender' => $this->institution->uuid])->assertOk();

    expect($request->fresh()->status)->toBe('merged')
        ->and((int) $request->fresh()->lender_id)->toBe($this->institution->id)
        ->and(($this->told)($this->farmerUser)->message)->toBe('Nsawam Community Bank is in the list as Sika Microfinance. You can now select it')
        ->and(Lender::count())->toBe(1)
        ->and(AuditLog::where('action', 'lender_request.merged')->count())->toBe(1);
});

test('using an existing lender needs a lender that is in the list', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201);
    $uuid = LenderRequest::first()->uuid;

    ($this->decide)($uuid, 'use-existing')->assertStatus(422);
    ($this->decide)($uuid, 'use-existing', ['lender' => (string) \Illuminate\Support\Str::uuid()])->assertStatus(422)->assertJsonPath('errors.lender.0', 'Lender is not listed');

    expect(LenderRequest::first()->status)->toBe('pending');
});

test('rejecting needs a reason, closes the request and tells the person with the reason', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201);
    $request = LenderRequest::first();

    ($this->decide)($request->uuid, 'reject')->assertStatus(422)->assertJsonValidationErrors('reason');
    ($this->decide)($request->uuid, 'reject', ['reason' => str_repeat('x', 501)])->assertStatus(422);
    ($this->decide)($request->uuid, 'reject', ['reason' => 'Not a real bank'])->assertOk();

    expect($request->fresh()->status)->toBe('rejected')
        ->and($request->fresh()->decision_note)->toBe('Not a real bank')
        ->and(($this->told)($this->farmerUser)->message)->toBe('Nsawam Community Bank was not added: Not a real bank')
        ->and(Lender::count())->toBe(1)
        ->and(AuditLog::where('action', 'lender_request.rejected')->count())->toBe(1);
});

test('an answered request cannot be answered again', function () {
    ($this->ask)($this->farmerUser)->assertStatus(201);
    $uuid = LenderRequest::first()->uuid;
    ($this->decide)($uuid, 'reject', ['reason' => 'No'])->assertOk();

    ($this->decide)($uuid, 'add')->assertStatus(422);
    ($this->decide)($uuid, 'use-existing', ['lender' => $this->institution->uuid])->assertStatus(422);
    ($this->decide)($uuid, 'reject', ['reason' => 'Again'])->assertStatus(422);

    expect(LenderRequest::first()->status)->toBe('rejected')->and(Notification::where('kind', 'loan.lender_request')->count())->toBe(1);
});

test('only an admin answers', function (string $who) {
    ($this->ask)($this->farmerUser)->assertStatus(201);
    $user = ['farmer' => $this->farmerUser, 'agent' => $this->agent, 'vet' => $this->vet][$who];

    $this->actingAs($user)->postJson('/admin/lender-requests/' . LenderRequest::first()->uuid . '/add')->assertForbidden();

    expect(LenderRequest::first()->status)->toBe('pending');
})->with(['farmer', 'agent', 'vet']);

// --- the loan form ---

test('the loan form tells an agent which farmer a request is for, and tells a farmer nothing', function () {
    $agent = $this->actingAs($this->agent)->get("/agent/farmers/{$this->profile->uuid}/loans/create")->viewData('page')['props'];
    $farmer = $this->actingAs($this->farmerUser)->get('/loans/create')->viewData('page')['props'];

    expect($agent['requestFor'])->toBe($this->profile->uuid)->and($farmer['requestFor'])->toBeNull();
});
