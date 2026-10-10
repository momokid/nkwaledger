<?php

// loan money in keeps the Liability class; principal paid back is the new "Loan repayment" class on the
// money-out side; a non-cash record carries its own amount and is never in cash, the balance or the class
// totals. The templates here are test-only; nothing is seeded.

require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';
require_once __DIR__ . '/../../Support/FigureSnapshot.php';

use App\Enums\MoneyClass;
use App\Models\FarmerProfile;
use App\Services\Ledger\Reports\AccountStatementService;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;

beforeEach(function () {
    buildLockedFigures($this);
    wireFiguresToAnAgent($this);
    addLoanTestTemplates($this);
    addNonCashTestTemplates($this);

    $this->borrower = FarmerProfile::factory()->create();
    $this->borrowerUnit = ($this->unitOf)($this->borrower);

    $put = fn($template, $amount) => ($this->put)($this->borrower, $this->borrowerUnit, $template, $amount);

    $put($this->saleTemplate, '1000');
    $put($this->feedTemplate, '400');
    $this->loanIn = $put($this->loanReceivedTemplate, '500');
    $put($this->loanRepaidTemplate, '200');
    $this->cancelledRepayment = $put($this->loanRepaidTemplate, '80');
    $this->goodsLoan = $put($this->goodsLoanTemplate, '700');
    $put($this->feedInKindTemplate, '150');
    $put($this->produceInKindTemplate, '90');
    $this->cancelledGoods = $put($this->goodsLoanTemplate, '60');

    ($this->cancel)($this->cancelledRepayment);
    ($this->cancel)($this->cancelledGoods);

    $this->statement = fn() => app(AccountStatementService::class)->for($this->borrower->id, $this->from, $this->to);
    $this->row = fn(string $reference) => collect(($this->statement)()->rows)->firstWhere('reference', $reference);
});

test('the new class is called Loan repayment', function () {
    expect(MoneyClass::LoanRepayment->value)->toBe('loan_repayment')
        ->and(MoneyClass::LoanRepayment->label())->toBe('Loan repayment');
});

test('loan money in is Liability, principal paid back is Loan repayment, and a correction takes its original class', function () {
    $s = ($this->statement)();
    $classOf = fn($reference) => ($this->row)($reference)->moneyClass;

    $correction = collect($s->rows)->where('cancelState', 'correction')->first(fn($row) => $row->moneyInMinor === 8000);

    expect($classOf($this->loanIn->reference))->toBe(MoneyClass::Liability)
        ->and($classOf($this->cancelledRepayment->reference))->toBe(MoneyClass::LoanRepayment)
        ->and($correction->moneyClass)->toBe(MoneyClass::LoanRepayment);
});

test('the class totals: liability 500.00, loan repayment 200.00 + the cancelled 80.00, and no correction row in them', function () {
    $s = ($this->statement)();

    expect([$s->totalLiabilityMinor, $s->totalLoanRepaymentMinor, $s->totalIncomeMinor, $s->totalExpenditureMinor, $s->totalAssetsMinor])
        ->toBe([50000, 28000, 100000, 40000, 0]);
});

test('assets + expenditure + loan repayment = money out, and income + liability = money in', function () {
    $s = ($this->statement)();

    expect($s->totalAssetsMinor + $s->totalExpenditureMinor + $s->totalLoanRepaymentMinor)->toBe($s->totalOutMinor)
        ->and($s->totalIncomeMinor + $s->totalLiabilityMinor)->toBe($s->totalInMinor)
        ->and([$s->totalInMinor, $s->totalOutMinor])->toBe([150000, 68000]);
});

test('closing = opening + money in - money out - cancelled money in + cancelled money out, with loans and non-cash records', function () {
    $s = ($this->statement)();

    expect($s->closingBalanceMinor)->toBe(
        $s->openingBalanceMinor + $s->totalInMinor - $s->totalOutMinor - $s->cancelledInMinor + $s->cancelledOutMinor,
    )->and([$s->cancelledInMinor, $s->cancelledOutMinor])->toBe([0, 8000])
        ->and($s->closingBalanceMinor)->toBe(90000);
});

test('a non-cash row carries its own amount, has no money in or out and no class', function () {
    $row = ($this->row)($this->goodsLoan->reference);

    expect([$row->isNonCash, $row->nonCashMinor, $row->moneyInMinor, $row->moneyOutMinor, $row->moneyClass])
        ->toBe([true, 70000, 0, 0, null]);
});

test('the non-cash subtotal counts the live non-cash records and not the cancelled one or its correction', function () {
    $s = ($this->statement)();
    $cancelled = ($this->row)($this->cancelledGoods->reference);

    expect($s->nonCashMinor)->toBe(70000 + 15000 + 9000)
        ->and([$cancelled->isNonCash, $cancelled->nonCashMinor, $cancelled->cancelState])->toBe([true, 6000, 'cancelled'])
        ->and(collect($s->rows)->where('cancelState', 'correction')->every(fn($row) => ! $row->isNonCash))->toBeTrue();
});

test('no non-cash record is cash collected or cash paid out, but each still counts as income or expense earned', function () {
    $r = app(IncomeAndExpenditureService::class)->for($this->borrower->id, $this->from, $this->to);

    expect([$r->totalIncomeMinor, $r->totalExpenseMinor, $r->cashCollectedMinor, $r->cashPaidOutMinor])
        ->toBe([109000, 55000, 100000, 40000]);
});

test('a farmer with no loan or non-cash records has zero for both new totals', function () {
    $s = app(AccountStatementService::class)->for($this->clean->id, $this->from, $this->to);

    expect([$s->totalLoanRepaymentMinor, $s->nonCashMinor])->toBe([0, 0]);
});
