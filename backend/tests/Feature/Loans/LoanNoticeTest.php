<?php

// The "not checked with the lender" notice belongs to each loan. On the Reports page that means each loan's own
// row; the loan flag is only a label on the row and never changes an amount, a balance or a total.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Loan;
use App\Services\Ledger\Reports\AccountStatementService;

const NOTICE = 'You entered this loan yourself. It has not been checked with the lender.';

beforeEach(function () {
    buildLoanWorld($this);

    $this->report = function () {
        $props = null;
        $this->actingAs($this->farmerUser)->get("/my-reports?from={$this->from}&to={$this->to}")->assertOk()->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props['report'] ?? $props;
    };

    $this->loanRows = fn() => collect(($this->report)()['rows'])->where('is_loan', true)->values();
    $this->printed = fn() => $this->actingAs($this->farmerUser)->get("/my-reports/print?from={$this->from}&to={$this->to}")->assertOk()->getContent();
});

test('no loan row on the Reports page and no notice in the print view when the farmer has no loans', function () {
    expect(($this->loanRows)())->toHaveCount(0)
        ->and(substr_count(($this->printed)(), NOTICE))->toBe(0);
});

test('one loan: one flagged row on the Reports page and one notice in the print view', function () {
    ($this->openLoan)();

    $rows = ($this->loanRows)();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['description'])->not->toContain('lender')
        ->and(substr_count(($this->printed)(), NOTICE))->toBe(1);
});

test('two loans: two flagged rows and two notices', function () {
    ($this->openLoan)();
    ($this->openLoan)(['lender_name' => 'Uncle Kofi']);

    expect(($this->loanRows)())->toHaveCount(2)
        ->and(substr_count(($this->printed)(), NOTICE))->toBe(2);
});

test('an opening entry is a loan row too', function () {
    app(\App\Services\Loans\LoanService::class)->record($this->profile, $this->farmerUser, ($this->existingEntry)());

    expect(($this->loanRows)())->toHaveCount(1);
});

test('a loan an agent recorded and the farmer accepted gets the same flag and notice', function () {
    $entry = ($this->pendingMoney)();
    $this->entries->accept($entry, $this->farmerUser, 'app');

    expect(($this->loanRows)())->toHaveCount(1)
        ->and(substr_count(($this->printed)(), NOTICE))->toBe(1);
});

test('a repayment is not a loan: its rows carry no flag and no notice', function () {
    $loan = ($this->openLoan)();
    ($this->repay)($loan, '600');

    expect(($this->loanRows)())->toHaveCount(1)
        ->and(collect(($this->report)()['rows'])->count())->toBeGreaterThan(1)
        ->and(substr_count(($this->printed)(), NOTICE))->toBe(1);
});

test('the CSV does not carry the notice', function () {
    ($this->openLoan)();

    $csv = $this->actingAs($this->farmerUser)->get("/my-reports/csv?from={$this->from}&to={$this->to}")->assertOk()->getContent();

    expect($csv)->not->toContain('checked with the lender');
});

test('the flag changes no amount, balance, total or signing code', function () {
    ($this->openLoan)();
    $loan = Loan::first();

    $statement = app(AccountStatementService::class)->for($this->profile->id, $this->from, $this->to, includeProvisional: true);
    $report = ($this->report)();

    expect($report['total_liability'])->toBe($statement->totalLiabilityMinor)
        ->and($report['closing_balance'])->toBe($statement->closingBalanceMinor)
        ->and($report['total_in'])->toBe($statement->totalInMinor)
        ->and($report['header']['verification_code'] ?? $statement->header->verificationCode)->toBe($statement->header->verificationCode)
        ->and($loan->balanceMinor())->toBe(100000);
});
