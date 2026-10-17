<?php

// A loan's balance is everything still owed on it: the principal and the interest together. It is 0 only when both
// are paid, and only then does the loan read Repaid.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Loan;
use App\Services\Loans\LoanService;

beforeEach(function () {
    buildLoanWorld($this);

    // principal 100000, interest 20000
    $this->loan = ($this->openLoan)();
    $this->read = fn() => Loan::find($this->loan->id);
});

test('a new loan owes its principal and its interest together', function () {
    expect(($this->read)()->balanceMinor())->toBe(120000)
        ->and(($this->read)()->displayStatus())->toBe('active');
});

test('a part payment takes its share off both, and the balance is what is left of both', function () {
    // 600 of 1200 owed: 500 principal and 100 interest
    ($this->repay)($this->loan, '600');

    expect(($this->read)()->balanceMinor())->toBe(60000)
        ->and(($this->read)()->remainingPrincipalMinor())->toBe(50000)
        ->and(($this->read)()->remainingInterestMinor())->toBe(10000)
        ->and(($this->read)()->displayStatus())->toBe('active');
});

test('a loan whose principal is paid but whose interest is not is still owed and not Repaid', function () {
    // the agreed interest is raised after the principal was cleared
    ($this->repay)($this->loan, '1200');
    Loan::whereKey($this->loan->id)->update(['agreed_interest_minor' => 30000]);

    expect(($this->read)()->remainingPrincipalMinor())->toBe(0)
        ->and(($this->read)()->balanceMinor())->toBe(10000)
        ->and(($this->read)()->displayStatus())->toBe('active');
});

test('a loan with both paid owes nothing and reads Repaid', function () {
    ($this->repay)($this->loan, '1200');

    expect(($this->read)()->balanceMinor())->toBe(0)
        ->and(($this->read)()->displayStatus())->toBe('repaid');
});

test('a cancelled loan owes nothing', function () {
    Loan::whereKey($this->loan->id)->update(['status' => Loan::CANCELLED]);

    expect(($this->read)()->balanceMinor())->toBe(0)->and(($this->read)()->displayStatus())->toBe('cancelled');
});

test('the total owed on the dashboard adds up the principal and the interest of every active loan', function () {
    $second = ($this->openLoan)(['lender_name' => 'Second Lender', 'amount' => '500', 'interest' => '50']);
    ($this->repay)($this->loan, '600');

    expect(app(LoanService::class)->totalOwedMinor($this->profile))->toBe(60000 + 55000)
        ->and(($this->read)()->balanceMinor() + Loan::find($second->id)->balanceMinor())->toBe(115000);
});

test('the Loans page, the loan page and the dashboard all show the same balance', function () {
    ($this->repay)($this->loan, '600');
    $loans = $this->actingAs($this->farmerUser)->get('/loans')->viewData('page')['props']['loans'];
    $detail = $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->viewData('page')['props']['loan'];

    expect(collect($loans)->firstWhere('uuid', $this->loan->uuid)['balance'])->toBe(60000)
        ->and($detail['balance'])->toBe(60000);
});
