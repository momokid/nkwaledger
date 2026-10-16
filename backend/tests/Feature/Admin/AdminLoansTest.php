<?php

// The admin's Credit menu: every farmer's loans in one list, read-only, with the waiting entries beside them.
// Admin only. Nothing on these pages links into a farmer's own pages, and no row id leaves the server.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Loan;
use App\Services\Ledger\ReversalService;
use App\Services\Loans\LoanService;
use App\Services\NavigationAccessService;

beforeEach(function () {
    buildLoanWorld($this);

    $this->loans = app(LoanService::class);
    // the main farmer's loan: 1000 principal, 200 interest; the other farmer's: 500 principal, 50 interest
    $this->mine = ($this->openLoan)(['lender_name' => 'Sika Microfinance']);
    $this->theirs = $this->loans->record($this->clean, $this->clean->user, ($this->moneyEntry)(['lender_name' => 'Asante Rural Bank', 'lender_type' => 'rural_bank', 'amount' => '500', 'interest' => '50']));
    $this->listJson = fn(string $query = '') => $this->actingAs($this->admin)->getJson('/admin/loans' . $query)->assertOk()->json();
    $this->farmerName = trim("{$this->farmerUser->surname} {$this->farmerUser->first_name}");
});

test('only an admin may open the loans list and a loan', function (string $who, int $status) {
    $user = ['admin' => $this->admin, 'farmer' => $this->farmerUser, 'agent' => $this->agent, 'vet' => $this->vet, 'adviser' => $this->adviser][$who];

    $this->actingAs($user)->get('/admin/loans')->assertStatus($status);
    $this->actingAs($user)->get("/admin/loans/{$this->mine->uuid}")->assertStatus($status);
})->with([
    'admin' => ['admin', 200],
    'farmer' => ['farmer', 403],
    'agent' => ['agent', 403],
    'vet' => ['vet', 403],
    'adviser' => ['adviser', 403],
]);

test('the list shows every farmer\'s loans, newest first, with the balance as principal plus interest', function () {
    $rows = collect(($this->listJson)()['rows']);

    expect($rows->pluck('lender')->all())->toBe(['Asante Rural Bank', 'Sika Microfinance'])
        ->and($rows->pluck('amount')->all())->toBe([50000, 100000])
        ->and($rows->pluck('balance')->all())->toBe([55000, 120000])
        ->and($rows->pluck('status')->all())->toBe(['active', 'active'])
        ->and($rows[1]['farmer'])->toBe($this->farmerName);
});

test('the total owed adds the principal and interest still owed on the active loans only', function () {
    ($this->repay)($this->mine, '600');
    $cancelled = ($this->openLoan)(['lender_name' => 'Gone Bank']);
    Loan::whereKey($cancelled->id)->update(['status' => Loan::CANCELLED]);
    $paid = ($this->openLoan)(['lender_name' => 'Paid Bank', 'amount' => '100', 'interest' => '10']);
    ($this->repay)($paid, '110');

    $expected = $this->loans->totalOwedMinor($this->profile) + $this->loans->totalOwedMinor($this->clean);

    expect(($this->listJson)()['total_owed'])->toBe($expected)->toBe(60000 + 55000);
});

test('the status filter splits active, repaid and cancelled, and the waiting entries have their own filter', function () {
    $paid = ($this->openLoan)(['lender_name' => 'Paid Bank', 'amount' => '100', 'interest' => '10']);
    ($this->repay)($paid, '110');
    $gone = ($this->openLoan)(['lender_name' => 'Gone Bank']);
    Loan::whereKey($gone->id)->update(['status' => Loan::CANCELLED]);
    $waiting = $this->entries->recordLoan($this->profile, $this->agent, ($this->moneyEntry)(['lender_name' => 'Waiting Bank']));

    $names = fn(string $status) => collect(($this->listJson)("?status={$status}")['rows'])->pluck('lender')->sort()->values()->all();

    expect($names('active'))->toBe(['Asante Rural Bank', 'Sika Microfinance'])
        ->and($names('repaid'))->toBe(['Paid Bank'])
        ->and($names('cancelled'))->toBe(['Gone Bank'])
        ->and($names('all'))->toBe(['Asante Rural Bank', 'Gone Bank', 'Paid Bank', 'Sika Microfinance'])
        ->and($names('waiting'))->toBe(['Waiting Bank']);

    $entry = ($this->listJson)('?status=waiting')['rows'][0];

    expect($entry['row'])->toBe('entry')->and($entry['status'])->toBe('pending')->and($entry['uuid'])->toBe($waiting->uuid);
});

test('a search matches the farmer\'s name or the lender, one word or several', function () {
    $find = fn(string $q) => collect(($this->listJson)('?search=' . urlencode($q))['rows'])->pluck('lender')->all();

    expect($find('sika'))->toBe(['Sika Microfinance'])
        ->and($find('RURAL'))->toBe(['Asante Rural Bank'])
        ->and($find($this->farmerUser->first_name))->toBe(['Sika Microfinance'])
        ->and($find("{$this->farmerUser->surname} sika"))->toBe(['Sika Microfinance'])
        ->and($find("{$this->farmerUser->surname} rural"))->toBe([])
        ->and($find('100%'))->toBe([]);
});

test('the list is paged 25 at a time', function () {
    foreach (range(1, 24) as $i) {
        $this->loans->record($this->clean, $this->clean->user, ($this->moneyEntry)(['lender_name' => "Bank {$i}", 'amount' => '10']));
    }

    $first = ($this->listJson)();
    $second = ($this->listJson)('?page=2');

    expect($first['rows'])->toHaveCount(25)->and($first['pagination'])->toBe(['page' => 1, 'last' => 2])
        ->and($second['rows'])->toHaveCount(1)->and($second['pagination'])->toBe(['page' => 2, 'last' => 2]);
});

test('the page opens with the first page and the figures already in it, and no row id', function () {
    $props = $this->actingAs($this->admin)->get('/admin/loans')->viewData('page')['props'];

    expect($props['rows'])->toHaveCount(2)
        ->and($props['total_owed'])->toBe(175000)
        ->and($props['filters'])->toBe(['search' => '', 'status' => 'all'])
        ->and(json_encode([$props['rows'], $props['pagination']]))->not->toContain('"id"')->not->toContain('farmer_profile_id');
});

test('the figures in the list are the same as the ones worked out loan by loan', function () {
    ($this->repay)($this->mine, '600');
    ($this->repay)($this->mine, '300');
    $paid = ($this->openLoan)(['lender_name' => 'Paid Bank', 'amount' => '100', 'interest' => '10']);
    $payment = ($this->repay)($paid, '110');
    $cancel = app(ReversalService::class);
    $cancel->approve($cancel->request(\App\Models\Transaction::find($payment->principal_transaction_id), $this->clerk, 'Typed wrongly'), $this->approver);

    $rows = collect(($this->listJson)('?status=all')['rows'])->keyBy('uuid');

    foreach (Loan::all() as $loan) {
        expect([$rows[$loan->uuid]['balance'], $rows[$loan->uuid]['status']])->toBe([$loan->balanceMinor(), $loan->displayStatus()]);
    }
});

test('the loan page records no money: it shows the repayments, the farmer\'s name and the lender, and carries nothing to add a repayment', function () {
    ($this->repay)($this->mine, '600');

    $props = $this->actingAs($this->admin)->get("/admin/loans/{$this->mine->uuid}")->viewData('page')['props'];

    expect($props['loan']['lender'])->toBe('Sika Microfinance')
        ->and($props['loan']['balance'])->toBe(60000)
        ->and($props['farmer'])->toBe($this->farmerName)
        ->and($props['repayments'])->toHaveCount(1)
        ->and([$props['repayments'][0]['amount'], $props['repayments'][0]['principal'], $props['repayments'][0]['interest']])->toBe([60000, 50000, 10000])
        ->and(array_keys($props))->not->toContain('postUrl')->not->toContain('previewUrl')->not->toContain('paidFrom');
});

test('nothing on the admin pages sends the admin into a farmer\'s own pages', function () {
    $list = json_encode($this->actingAs($this->admin)->get('/admin/loans')->viewData('page')['props']);
    $page = json_encode($this->actingAs($this->admin)->get("/admin/loans/{$this->mine->uuid}")->viewData('page')['props']);

    foreach ([$list, $page] as $props) {
        expect($props)->not->toContain('"\/loans')->not->toContain('\/farmer\/')->not->toContain('\/my-');
    }
});

test('the admin menu has a Credit group with Loans and Lenders, for an admin only', function () {
    $menu = collect(app(NavigationAccessService::class)->adminMenu($this->admin));
    $credit = $menu->firstWhere('label', 'Credit');

    expect($credit)->not->toBeNull()
        ->and(collect($credit['children'])->pluck('label')->all())->toBe(['Loans', 'Lenders'])
        ->and(collect($credit['children'])->pluck('routeName')->all())->toBe(['admin.loans.index', 'admin.lenders.index'])
        ->and(app(NavigationAccessService::class)->adminMenu($this->farmerUser))->toBe([])
        ->and(app(NavigationAccessService::class)->adminMenu($this->agent))->toBe([]);
});
