<?php

// The loan page starts with the loan itself, ahead of the repayments: the day it was granted, the amount and the
// interest agreed. Farmer, agent and admin all get it from the same loan summary.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Services\Loans\LoanService;

beforeEach(function () {
    buildLoanWorld($this);

    $this->loan = ($this->openLoan)(['date' => '2026-09-01']);
    $this->pages = fn() => [
        'farmer' => $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->viewData('page')['props']['loan'],
        'agent' => $this->actingAs($this->agent)->get("/agent/loans/{$this->loan->uuid}")->viewData('page')['props']['loan'],
        'admin' => $this->actingAs($this->admin)->get("/admin/loans/{$this->loan->uuid}")->viewData('page')['props']['loan'],
    ];
});

test('all three loan pages carry the day the loan was granted, its amount and the interest agreed', function () {
    foreach (($this->pages)() as $loan) {
        expect($loan['granted_on'])->toBe('2026-09-01')
            ->and($loan['amount'])->toBe(100000)
            ->and($loan['agreed_interest'])->toBe(20000);
    }
});

test('a loan with no interest agreed carries none', function () {
    $loan = app(LoanService::class)->record($this->profile, $this->farmerUser, ($this->moneyEntry)(['amount' => '300']));

    $props = $this->actingAs($this->farmerUser)->get("/loans/{$loan->uuid}")->viewData('page')['props']['loan'];

    expect($props['agreed_interest'])->toBeNull()->and($props['amount'])->toBe(30000);
});

test('a loan the farmer already had is dated the day it was recorded', function () {
    $loan = app(LoanService::class)->record($this->profile, $this->farmerUser, ($this->existingEntry)());

    $props = $this->actingAs($this->farmerUser)->get("/loans/{$loan->uuid}")->viewData('page')['props']['loan'];

    expect($props['granted_on'])->toBe(now()->toDateString());
});

test('the granted details show what was granted, not what is still owed', function () {
    ($this->repay)($this->loan, '600');

    foreach (($this->pages)() as $loan) {
        expect($loan['amount'])->toBe(100000)->and($loan['agreed_interest'])->toBe(20000)->and($loan['balance'])->toBe(60000);
    }
});
