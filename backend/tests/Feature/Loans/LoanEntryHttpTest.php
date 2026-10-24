<?php

// Task 3.6b, parts B and C: the routes and screens for an agent's loan entry, and who may use each of them.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\PendingEntry;
use App\Models\PendingEntryCode;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    buildLoanWorld($this);
    $this->freezeTime();

    $this->entry = ($this->pendingMoney)();

    // the people, by name, and what they may do
    $this->actors = [
        'agent' => $this->agent,
        'otherAgent' => $this->otherAgent,
        'admin' => $this->admin,
        'farmer' => $this->farmerUser,
        'otherFarmer' => $this->otherFarmerUser,
        'vet' => $this->vet,
        'adviser' => $this->adviser,
    ];

    $this->props = function ($user, string $url) {
        $props = null;

        $this->actingAs($user)->get($url)->assertOk()->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray();
        });

        return $props;
    };

    $this->as = fn(string $who) => $this->actingAs($this->actors[$who]);
    $this->asGuest = function () {
        Auth::forgetGuards();

        return $this;
    };
    $this->form = fn(array $over = []) => array_merge([
        'kind' => 'money', 'lender_name' => 'Sika Microfinance', 'lender' => institutionNamed('Sika Microfinance', 'microfinance')->uuid, 'amount' => '200',
        'date' => now()->toDateString(), 'paid_into' => $this->cash->id, 'idempotency_key' => 'k-' . uniqid(),
    ], $over);
    $this->farmerUrl = "/agent/farmers/{$this->profile->uuid}";
    // an agent's money loan names the farms too
    $this->agentForm = fn(array $over = []) => ($this->form)(['farms' => wholeLoanOnOneFarm($this->profile, '200')] + $over);
});

// --- the access matrix: agent routes ---

test('the agent routes: who gets what', function (string $who, int $createStatus, int $storeStatus) {
    ($this->as)($who)->get("{$this->farmerUrl}/loans/create")->assertStatus($createStatus);
    ($this->as)($who)->get("{$this->farmerUrl}/loans/existing/create")->assertStatus($createStatus);
    ($this->as)($who)->post("{$this->farmerUrl}/loans", ($this->agentForm)())->assertStatus($storeStatus);
})->with([
    'the assigned agent' => ['agent', 200, 302],
    'an agent who is not assigned' => ['otherAgent', 404, 404],
    'an admin' => ['admin', 403, 403],
    'the farmer' => ['farmer', 403, 403],
    'another farmer' => ['otherFarmer', 403, 403],
    'a vet' => ['vet', 403, 403],
    'an adviser' => ['adviser', 403, 403],
]);

test('the agent entry actions: who gets what', function (string $route, array $body) {
    $url = fn() => "/agent/loan-entries/{$this->entry->uuid}/{$route}";
    $send = fn(string $who) => ($this->as)($who)->post($url(), $body);

    expect($send('otherAgent')->status())->toBe(404)
        ->and($send('admin')->status())->toBe(403)
        ->and($send('farmer')->status())->toBe(403)
        ->and($send('otherFarmer')->status())->toBe(403)
        ->and($send('vet')->status())->toBe(403)
        ->and($send('adviser')->status())->toBe(403)
        ->and(($this->asGuest)()->post($url(), $body)->status())->toBe(302)
        ->and($send('agent')->status())->toBe(302);
})->with([
    'send the code' => ['send-code', []],
    'enter a code' => ['code', ['code' => '123456']],
    'withdraw' => ['withdraw', []],
]);

test('the farmer routes: who gets what', function () {
    $url = fn(string $action) => "/loans/entries/{$this->entry->uuid}/{$action}";

    foreach (['accept', 'decline'] as $action) {
        foreach (['agent', 'admin', 'vet', 'adviser'] as $who) {
            expect(($this->as)($who)->post($url($action))->status())->toBe(403, "{$who} on {$action}");
        }

        expect(($this->as)('otherFarmer')->post($url($action))->status())->toBe(404, "other farmer on {$action}");
        expect(($this->asGuest)()->post($url($action))->status())->toBe(302);
    }

    expect($this->entry->fresh()->status)->toBe('pending');
    expect(($this->as)('farmer')->post($url('accept'))->status())->toBe(302)
        ->and($this->entry->fresh()->status)->toBe('accepted');
});

test('after the farmer is reassigned the new agent controls the entries and the old agent gets 404', function () {
    $this->profile->update(['assigned_agent_id' => $this->otherAgent->id]);

    $url = "/agent/loan-entries/{$this->entry->uuid}/withdraw";

    expect(($this->as)('agent')->post($url)->status())->toBe(404)
        ->and(($this->as)('agent')->get("{$this->farmerUrl}/loans/create")->status())->toBe(404)
        ->and($this->entry->fresh()->status)->toBe('pending')
        ->and(($this->as)('otherAgent')->post($url)->status())->toBe(302)
        ->and($this->entry->fresh()->status)->toBe('withdrawn');
});

test('an agent can still record for a locked farmer and use the code path', function () {
    $this->farmerUser->forceFill(['is_active' => false])->save();

    ($this->as)('agent')->post("{$this->farmerUrl}/loans", ($this->agentForm)())->assertRedirect($this->farmerUrl);

    ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/send-code")->assertRedirect();
    $code = ($this->lastCode)();
    ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/code", ['code' => $code])->assertSessionHasNoErrors();

    expect($this->entry->fresh()->status)->toBe('accepted');
});

test('the send and enter routes are throttled', function () {
    $statuses = [];

    for ($i = 0; $i < 12; $i++) {
        $statuses[] = ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/code", ['code' => '000000'])->status();
    }

    expect($statuses)->toContain(429)->and($statuses[0])->toBe(302);

    $this->travel(2)->minutes();
    $sent = ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/send-code")->status();

    expect($sent)->toBe(302);
});

// --- the agent records ---

test('the agent form is the farmer form with the farmer address, and the existing-loan amount reads Loan Amount', function () {
    $money = ($this->props)($this->agent, "{$this->farmerUrl}/loans/create");
    $existing = ($this->props)($this->agent, "{$this->farmerUrl}/loans/existing/create");

    expect([$money['component'], $money['props']['kind'], $money['props']['postUrl'], $money['props']['backUrl']])
        ->toBe(['Loans/Create', 'money', "{$this->farmerUrl}/loans", $this->farmerUrl])
        ->and([$existing['props']['kind'], $existing['props']['agent']])->toBe(['existing', true]);
});

test('the agent records an entry with the same rules and the same words as the farmer form', function () {
    ($this->as)('agent')->post("{$this->farmerUrl}/loans", ($this->form)(['amount' => '0']))
        ->assertSessionHasErrors(['amount' => 'Enter an amount above zero.']);
    ($this->as)('agent')->post("{$this->farmerUrl}/loans", ($this->form)(['lender' => '']))
        ->assertSessionHasErrors(['lender' => 'Lender is not listed']);
    ($this->as)('agent')->post("{$this->farmerUrl}/loans", ($this->form)(['date' => now()->addDay()->toDateString()]))
        ->assertSessionHasErrors(['date' => 'This date is in the future.']);

    expect(PendingEntry::count())->toBe(1);

    ($this->as)('agent')->post("{$this->farmerUrl}/loans", ($this->agentForm)())->assertRedirect($this->farmerUrl);

    expect(PendingEntry::count())->toBe(2)->and(Loan::count())->toBe(0);
});

test('a farmer own loan needs no acceptance', function () {
    $this->actingAs($this->farmerUser)->post('/loans', ($this->form)(['farms' => wholeLoanOnOneFarm($this->profile, '200')]))->assertRedirect('/loans');

    expect(Loan::count())->toBe(1)->and(PendingEntry::count())->toBe(1);
});

// --- the farmer's page ---

test('the farmer sees pending entries as cards with the lender, amount, date and who recorded it', function () {
    ($this->pendingExisting)();
    $withdrawn = ($this->pendingMoney)(['lender_name' => 'Gone Lender', 'lender' => institutionNamed('Gone Lender')->uuid]);
    $this->entries->withdraw($withdrawn, $this->agent);
    $declined = ($this->pendingMoney)(['lender_name' => 'Declined Lender', 'lender' => institutionNamed('Declined Lender')->uuid]);
    $this->entries->decline($declined, $this->farmerUser);

    $page = ($this->props)($this->farmerUser, '/loans')['props'];
    $agentName = $this->agent->surname . ' ' . $this->agent->first_name;

    expect(collect($page['pending'])->map(fn($card) => [$card['lender'], $card['amount'], $card['agent']])->all())
        ->toBe([['Uncle Kofi', 30000, $agentName], ['Sika Microfinance', 50000, $agentName]])
        ->and($page['pending'][1]['date'])->toBe(now()->toDateString())
        ->and(collect($page['pending'])->every(fn($card) => isset($card['uuid']) && ! array_key_exists('id', $card)))->toBeTrue()
        ->and($page['loans'])->toBe([]);
});

test('a farmer sees only their own pending entries', function () {
    expect(($this->props)($this->otherFarmerUser, '/loans')['props']['pending'])->toBe([]);
});

test('accepting in the app shows the loan in the list and the approved flash', function () {
    ($this->as)('farmer')->post("/loans/entries/{$this->entry->uuid}/accept")
        ->assertRedirect('/loans')->assertSessionHas('success', 'Loan accepted.');

    $page = ($this->props)($this->farmerUser, '/loans')['props'];

    expect($page['pending'])->toBe([])
        ->and(collect($page['loans'])->map(fn($loan) => [$loan['lender'], $loan['amount'], $loan['balance'], $loan['status']])->all())
        ->toBe([['Sika Microfinance', 50000, 50000, 'active']]);
});

test('declining shows the approved flash and nothing is made', function () {
    ($this->as)('farmer')->post("/loans/entries/{$this->entry->uuid}/decline")
        ->assertRedirect('/loans')->assertSessionHas('success', 'You declined this loan.');

    expect([$this->entry->fresh()->status, Loan::count()])->toBe(['declined', 0]);
});

test('accepting something that the checks refuse leaves it pending with the existing words', function () {
    \App\Models\AccountingPeriod::first()->close($this->admin);

    ($this->as)('farmer')->post("/loans/entries/{$this->entry->uuid}/accept")
        ->assertSessionHas('error', 'That period is closed. Record it against today instead.');

    expect([$this->entry->fresh()->status, Loan::count()])->toBe(['pending', 0]);
});

// --- the agent's page and the admin's ---

test('the agent sees the farmer loans and entries with status words, and can act', function () {
    ($this->pendingExisting)();
    $this->entries->decline(($this->pendingMoney)(['lender_name' => 'Declined Lender', 'lender' => institutionNamed('Declined Lender')->uuid]), $this->farmerUser);
    $this->entries->withdraw(($this->pendingMoney)(['lender_name' => 'Gone Lender', 'lender' => institutionNamed('Gone Lender')->uuid]), $this->agent);
    $accepted = ($this->pendingMoney)(['lender_name' => 'Accepted Lender', 'lender' => institutionNamed('Accepted Lender')->uuid]);
    $this->entries->accept($accepted, $this->farmerUser, 'app');

    $props = ($this->props)($this->agent, $this->farmerUrl)['props'];

    $rows = collect($props['loans'])->map(fn($row) => [$row['row'], $row['lender'], $row['status']])->all();

    expect($rows)->toContain(['entry', 'Sika Microfinance', 'pending'])
        ->toContain(['entry', 'Uncle Kofi', 'pending'])
        ->toContain(['entry', 'Declined Lender', 'declined'])
        ->toContain(['entry', 'Gone Lender', 'withdrawn'])
        ->toContain(['loan', 'Accepted Lender', 'active'])
        // an accepted entry is the loan itself, never listed twice
        ->and(collect($rows)->where(1, 'Accepted Lender')->count())->toBe(1)
        ->and($props['loan_actions'])->toBeTrue()
        ->and(collect($props['loans'])->every(fn($row) => ! array_key_exists('id', $row)))->toBeTrue();
});

test('an agent who is not assigned does not see the farmer page at all', function () {
    ($this->as)('otherAgent')->get($this->farmerUrl)->assertNotFound();
});

test('the admin sees the same rows and cannot act', function () {
    $props = ($this->props)($this->admin, "/admin/farmers/{$this->profile->uuid}")['props'];

    expect(collect($props['loans'])->pluck('status')->all())->toBe(['pending'])
        ->and($props['loan_actions'])->toBeFalse();
});

// --- acceptance with the code, over HTTP ---

test('the agent sends a code, types it, and the farmer entry is accepted by code with the approved flashes', function () {
    ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/send-code")
        ->assertSessionHas('success', 'Code sent to the farmer.');

    $code = ($this->lastCode)();

    ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/code", ['code' => $code])
        ->assertSessionHas('success', 'Loan accepted.')->assertSessionHasNoErrors();

    $loan = Loan::first();

    expect([$this->entry->fresh()->status, $loan->accepted_via, (int) $loan->recorded_by])->toBe(['accepted', 'code', $this->agent->id]);
});

test('a wrong, expired or locked code shows the approved words beside the code box', function () {
    ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/send-code");
    $code = ($this->lastCode)();
    $wrong = $code === '000000' ? '111111' : '000000';

    $enter = fn(string $value) => ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/code", ['code' => $value]);

    $enter($wrong)->assertSessionHasErrors(['code' => 'The code is not correct.']);
    $enter($wrong)->assertSessionHasErrors(['code' => 'The code is not correct.']);
    $enter($wrong)->assertSessionHasErrors(['code' => 'Too many wrong tries. Send a new code.']);

    ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/send-code");
    $this->travel(11)->minutes();

    $enter(($this->lastCode)())->assertSessionHasErrors(['code' => 'The code has expired. Send a new code.']);

    expect($this->entry->fresh()->status)->toBe('pending');
});

test('withdrawing shows the approved flash and the entry leaves the farmer page', function () {
    ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/withdraw")->assertSessionHas('success', 'Loan withdrawn.');

    expect(($this->props)($this->farmerUser, '/loans')['props']['pending'])->toBe([]);
});

test('sending a code to a farmer with no phone shows an error and sends nothing', function () {
    $this->profile->user->forceFill(['phone' => ''])->save();

    ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/send-code")->assertSessionHas('error');

    expect(app(\App\Contracts\SmsProvider::class)->sent)->toBe([])->and(PendingEntryCode::count())->toBe(0);
});

test('pending, accepted and declined entries leave the figures where they were until acceptance', function () {
    $form = ($this->agentForm)();
    $before = figureSnapshot($this, withLoanFigures: true);

    ($this->as)('agent')->post("{$this->farmerUrl}/loans", $form)->assertRedirect();
    ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/send-code");
    ($this->as)('agent')->post("/agent/loan-entries/{$this->entry->uuid}/code", ['code' => '000000']);

    expect(figureSnapshot($this, withLoanFigures: true))->toBe($before)
        ->and(Transaction::where('transaction_type', 'LOAN')->count())->toBe(0);
});
