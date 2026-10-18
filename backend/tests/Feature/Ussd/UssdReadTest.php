<?php

// USSD slice 2: from the menu a farmer reads a summary for a period they pick, and their loans. Read-only.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use App\Services\Loans\LoanService;
use App\Support\Money;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    buildLoanWorld($this);
    config(['services.ussd.secret' => 'test-secret']);

    $this->farmerUser->forceFill(['phone' => '0244445566', 'phone_verified_at' => now(), 'ussd_pin_hash' => Hash::make('4829')])->save();

    $this->dial = fn(string $input = '', bool $new = false) => $this->postJson('/ussd/callback/test-secret', [
        'sessionID' => 's-1', 'msisdn' => '233244445566', 'newSession' => $new, 'userData' => $input,
    ]);
    $this->signIn = function () {
        ($this->dial)('', true);
        ($this->dial)('4829');
    };
    $this->late = fn(int $daysPastDue, string $lender) => app(LoanService::class)->record($this->profile, $this->farmerUser, ($this->moneyEntry)([
        'lender_name' => $lender, 'date' => now()->subDays($daysPastDue + 30)->toDateString(), 'due_date' => now()->subDays($daysPastDue)->toDateString(),
    ]));
});

test('the menu lists Summary, Loans and Exit', function () {
    ($this->dial)('', true);
    $menu = ($this->dial)('4829')->json('message');

    expect($menu)->toContain('1. Summary')->toContain('2. Loans')->toContain('6. Exit');
});

test('Summary asks for a period, then shows income, expenses and net and ends', function () {
    ($this->signIn)();

    $asks = ($this->dial)('1');
    expect($asks->json('continueSession'))->toBeTrue()
        ->and($asks->json('message'))->toContain('1. Last 30 days')->toContain('2. This month')->toContain('3. This year');

    $shown = ($this->dial)('2');

    // the same figures the web dashboard gets for this month
    $report = app(IncomeAndExpenditureService::class)->for($this->profile->id, today()->startOfMonth()->toDateString(), today()->toDateString(), includeProvisional: true);

    expect($shown->json('continueSession'))->toBeFalse()
        ->and($shown->json('message'))->toContain('This month')
        ->toContain('Income: ' . Money::format($report->totalIncomeMinor))
        ->toContain('Expenses: ' . Money::format($report->totalExpenseMinor))
        ->toContain('Net: ' . Money::format($report->netMinor));
});

test('an answer that is not a period asks again', function () {
    ($this->signIn)();
    ($this->dial)('1');

    $again = ($this->dial)('9');

    expect($again->json('continueSession'))->toBeTrue()
        ->and($again->json('message'))->toContain('1. Last 30 days');
});

test('Loans shows the total owed and up to the 3 newest loans, with late ones marked', function () {
    foreach (['One Lender', 'Two Lender', 'Three Lender', 'Four Lender'] as $name) {
        app(LoanService::class)->record($this->profile, $this->farmerUser, ($this->moneyEntry)(['lender_name' => $name, 'amount' => '100']));
    }
    ($this->late)(10, 'Late Lender');
    ($this->signIn)();

    $loans = ($this->dial)('2');
    $text = $loans->json('message');

    expect($loans->json('continueSession'))->toBeFalse()
        ->and($text)->toContain('GHS 900.00')
        ->and($text)->toContain('Late Lender')->toContain('Overdue')
        ->and(substr_count($text, 'GHS'))->toBe(4)
        ->and($text)->not->toContain('One Lender');
});

test('with no loans it says so', function () {
    ($this->signIn)();

    expect(($this->dial)('2')->json('message'))->toContain('GHS 0.00');
});
