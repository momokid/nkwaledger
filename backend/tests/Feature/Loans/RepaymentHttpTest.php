<?php

// Task 3.7: the routes and pages for repayments, and who may use each of them. Out of scope is a 404.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\LoanRepayment;
use App\Models\PendingEntry;
use App\Models\Transaction;
use App\Services\Loans\RepaymentService;
use App\Support\Wording;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    buildLoanWorld($this);
    $this->loan = ($this->openLoan)();

    $this->actors = [
        'agent' => $this->agent,
        'otherAgent' => $this->otherAgent,
        'admin' => $this->admin,
        'farmer' => $this->farmerUser,
        'otherFarmer' => $this->otherFarmerUser,
        'vet' => $this->vet,
        'adviser' => $this->adviser,
    ];

    $this->as = fn(string $who) => $this->actingAs($this->actors[$who]);
    $this->asGuest = function () {
        Auth::forgetGuards();

        return $this;
    };

    // the form says cash, momo or bank: no ledger row id leaves the server
    $this->httpForm = fn(string $amount, array $over = []) => ($this->repayForm)($amount, array_merge(['paid_from' => 'cash'], $over));

    $this->farmerUrl = "/loans/{$this->loan->uuid}";
    $this->agentUrl = "/agent/loans/{$this->loan->uuid}/repayments";
});

test('the farmer routes: who gets what', function (string $who, int $show, int $preview, int $store) {
    $body = ($this->httpForm)('10');

    ($this->as)($who)->get($this->farmerUrl)->assertStatus($show);
    ($this->as)($who)->postJson("{$this->farmerUrl}/repayments/preview", ['amount' => '10'])->assertStatus($preview);
    ($this->as)($who)->post("{$this->farmerUrl}/repayments", $body)->assertStatus($store);
})->with([
    'the farmer' => ['farmer', 200, 200, 302],
    'another farmer' => ['otherFarmer', 404, 404, 404],
    'the farmer\'s agent' => ['agent', 404, 404, 404],
    'an admin' => ['admin', 404, 404, 404],
    'a vet' => ['vet', 404, 404, 404],
    'an adviser' => ['adviser', 404, 404, 404],
]);

test('the agent routes: who gets what', function (string $who, int $create, int $preview, int $store) {
    $body = ($this->httpForm)('10');

    ($this->as)($who)->get("{$this->agentUrl}/create")->assertStatus($create);
    ($this->as)($who)->postJson("{$this->agentUrl}/preview", ['amount' => '10'])->assertStatus($preview);
    ($this->as)($who)->post($this->agentUrl, $body)->assertStatus($store);
})->with([
    'the assigned agent' => ['agent', 200, 200, 302],
    'an agent who is not assigned' => ['otherAgent', 404, 404, 404],
    'an admin' => ['admin', 404, 404, 404],
    'the farmer' => ['farmer', 404, 404, 404],
    'another farmer' => ['otherFarmer', 404, 404, 404],
    'a vet' => ['vet', 404, 404, 404],
    'an adviser' => ['adviser', 404, 404, 404],
]);

test('a guest is sent to sign in', function () {
    expect(($this->asGuest)()->get($this->farmerUrl)->status())->toBe(302)
        ->and(($this->asGuest)()->post("{$this->farmerUrl}/repayments", ($this->httpForm)('10'))->status())->toBe(302)
        ->and(($this->asGuest)()->post($this->agentUrl, ($this->httpForm)('10'))->status())->toBe(302);
});

test('only the farmer\'s own agent posts as pending, and the farmer\'s own post is real', function () {
    ($this->as)('agent')->post($this->agentUrl, ($this->httpForm)('600'))->assertRedirect();

    expect(PendingEntry::where('entry_type', 'loan.repayment')->count())->toBe(1)
        ->and(LoanRepayment::count())->toBe(0);

    ($this->as)('farmer')->post("{$this->farmerUrl}/repayments", ($this->httpForm)('600'))->assertRedirect();

    expect(LoanRepayment::count())->toBe(1);
});

test('the loan page shows the loan, its repayments and the form values, with no row ids', function () {
    ($this->repay)($this->loan, '600');

    $props = null;
    ($this->as)('farmer')->get($this->farmerUrl)->assertOk()->assertInertia(function ($page) use (&$props) {
        $props = $page->toArray()['props'];
    });

    expect($props['loan']['uuid'])->toBe($this->loan->uuid)
        ->and($props['loan']['balance'])->toBe(60000)
        ->and($props['repayments'])->toHaveCount(1)
        ->and($props['repayments'][0]['amount'])->toBe(60000)
        ->and($props['repayments'][0]['principal'])->toBe(50000)
        ->and($props['repayments'][0]['interest'])->toBe(10000)
        ->and(collect($props['paidFrom'])->pluck('label')->all())->toBe(['Cash', 'MoMo', 'Bank'])
        ->and(collect($props['paidFrom'])->pluck('value')->all())->toBe(['cash', 'momo', 'bank'])
        ->and($props['idempotencyKey'])->not->toBeEmpty();

    // a numeric id of a loan, a repayment or a record never reaches the browser
    expect(json_encode([$props['loan'], $props['repayments'], $props['paidFrom']]))->not->toContain('"id"');
});

test('the preview shows the split the server would use, and changes nothing', function () {
    $before = [Transaction::count(), LoanRepayment::count()];

    ($this->as)('farmer')->postJson("{$this->farmerUrl}/repayments/preview", ['amount' => '600'])
        ->assertOk()
        ->assertJson(['principal' => 50000, 'interest' => 10000, 'excess' => 0]);

    ($this->as)('farmer')->postJson("{$this->farmerUrl}/repayments/preview", ['amount' => '1300'])
        ->assertStatus(422)
        ->assertJson(['message' => Wording::get(RepaymentService::OVER_TOTAL)]);

    expect([Transaction::count(), LoanRepayment::count()])->toBe($before);
});

test('a split sent by the client is ignored', function () {
    ($this->as)('farmer')->post("{$this->farmerUrl}/repayments", ($this->httpForm)('600', [
        'principal_minor' => 59999, 'interest_minor' => 1, 'principal' => 1,
    ]))->assertRedirect();

    $repayment = LoanRepayment::first();

    expect([$repayment->principal_minor, $repayment->interest_minor])->toBe([50000, 10000]);
});

test('an amount above what is owed is refused over HTTP, whatever confirmation is sent along', function () {
    ($this->as)('farmer')->post("{$this->farmerUrl}/repayments", ($this->httpForm)('1300'))->assertSessionHas('error', Wording::get(RepaymentService::OVER_TOTAL));
    ($this->as)('farmer')->post("{$this->farmerUrl}/repayments", ($this->httpForm)('1300', ['confirm_excess' => true]))->assertSessionHas('error', Wording::get(RepaymentService::OVER_TOTAL));

    expect(LoanRepayment::count())->toBe(0);
});

test('an agent cannot record an amount above what is owed, and the farmer is never asked about it', function () {
    ($this->as)('agent')->post($this->agentUrl, ($this->httpForm)('1300'))->assertSessionHas('error', Wording::get(RepaymentService::OVER_TOTAL));

    expect(PendingEntry::count())->toBe(0);
});

test('an agent cannot record an entry on a fully repaid loan', function () {
    ($this->as)('farmer')->post("{$this->farmerUrl}/repayments", ($this->httpForm)('1200'));
    ($this->as)('agent')->post($this->agentUrl, ($this->httpForm)('10'))->assertSessionHas('error', Wording::get(RepaymentService::FULLY_REPAID));

    expect(PendingEntry::count())->toBe(0);
});
test('the farmer\'s loans page shows the repayment waiting, without row ids', function () {
    ($this->as)('agent')->post($this->agentUrl, ($this->httpForm)('600'));

    $props = null;
    ($this->as)('farmer')->get('/loans')->assertOk()->assertInertia(function ($page) use (&$props) {
        $props = $page->toArray()['props'];
    });

    expect($props['pending'])->toHaveCount(1)
        ->and($props['pending'][0]['amount'])->toBe(60000)
        ->and($props['pending'][0]['kind'])->toBe('repayment')
        ->and(array_key_exists('excess', $props['pending'][0]))->toBeFalse();
});
