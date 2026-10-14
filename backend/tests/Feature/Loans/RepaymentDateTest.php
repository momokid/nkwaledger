<?php

// Task B1: a repayment dated before the loan date is refused, on the farmer's own path, on the agent's entry, and when
// the farmer accepts it. The loan's own date is the line; the same day is fine.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Exceptions\Ledger\PostingFailed;
use App\Models\LoanRepayment;
use App\Models\PendingEntry;
use App\Services\Loans\RepaymentService;

beforeEach(function () {
    buildLoanWorld($this);

    // the loan was received five days ago
    $this->loanDate = now()->subDays(5)->toDateString();
    $this->loan = ($this->openLoan)(['date' => $this->loanDate]);
    $this->before = now()->subDays(6)->toDateString();
});

test('the farmer\'s own repayment dated before the loan date is refused, and nothing is written', function () {
    expect(fn() => ($this->repay)($this->loan, '100', ['date' => $this->before]))->toThrow(PostingFailed::class, RepaymentService::DATE_BEFORE_LOAN)
        ->and(LoanRepayment::count())->toBe(0);
});

test('the same day as the loan, and any day after it, are allowed', function () {
    ($this->repay)($this->loan, '100', ['date' => $this->loanDate]);
    ($this->repay)($this->loan, '100', ['date' => now()->subDays(2)->toDateString()]);
    ($this->repay)($this->loan, '100', ['date' => now()->toDateString()]);

    expect(LoanRepayment::count())->toBe(3);
});

test('over HTTP the farmer is told with the key, and nothing is saved', function () {
    $this->actingAs($this->farmerUser)->post("/loans/{$this->loan->uuid}/repayments", ($this->repayForm)('100', ['date' => $this->before, 'paid_from' => 'cash']))
        ->assertSessionHas('error', RepaymentService::DATE_BEFORE_LOAN);

    expect(LoanRepayment::count())->toBe(0);
});

test('an agent\'s entry dated before the loan date is refused at entry, and nothing is stored', function () {
    expect(fn() => $this->entries->recordRepayment($this->loan, $this->agent, ($this->repayForm)('100', ['date' => $this->before])))
        ->toThrow(PostingFailed::class, RepaymentService::DATE_BEFORE_LOAN)
        ->and(PendingEntry::count())->toBe(0);

    $this->actingAs($this->agent)->post("/agent/loans/{$this->loan->uuid}/repayments", ($this->repayForm)('100', ['date' => $this->before, 'paid_from' => 'cash']))
        ->assertSessionHas('error', RepaymentService::DATE_BEFORE_LOAN);

    expect(PendingEntry::count())->toBe(0);
});

test('acceptance checks the date again: an entry that slipped in with an early date is refused and stays waiting', function () {
    $good = $this->entries->recordRepayment($this->loan, $this->agent, ($this->repayForm)('100', ['date' => $this->loanDate]));

    // as if it had been stored before the rule existed
    $good->forceFill(['payload' => array_merge($good->payload, ['date' => $this->before])])->save();

    expect(fn() => $this->entries->accept($good, $this->farmerUser, 'app'))->toThrow(PostingFailed::class, RepaymentService::DATE_BEFORE_LOAN)
        ->and($good->fresh()->status)->toBe('pending')
        ->and(LoanRepayment::count())->toBe(0);
});

test('an opening loan counts from the day it was entered', function () {
    $opening = app(\App\Services\Loans\LoanService::class)->record($this->profile, $this->farmerUser, ($this->existingEntry)(['lender_name' => 'Uncle Kofi']));

    expect(fn() => ($this->repay)($opening, '10', ['date' => now()->subDay()->toDateString()]))->toThrow(PostingFailed::class, RepaymentService::DATE_BEFORE_LOAN);

    ($this->repay)($opening, '10', ['date' => now()->toDateString()]);

    expect(LoanRepayment::count())->toBe(1);
});
