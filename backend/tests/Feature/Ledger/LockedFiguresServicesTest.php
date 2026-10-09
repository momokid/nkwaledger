<?php

// Locks today's figures from the ledger services, using the shared fixture in tests/Support.
// The cancelled sale, expense and payment are in the books on purpose.
// What each figure counts (and what it does not) is written beside the number.

require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';

use App\Enums\MoneyClass;
use App\Models\Transaction;
use App\Services\Ledger\CreditSettlementService;
use App\Services\Ledger\Reports\AccountStatementService;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use App\Services\Ledger\Reports\TrialBalanceService;

beforeEach(function () {
    buildLockedFigures($this);

    $this->statement = fn($farmer = null) => app(AccountStatementService::class)
        ->for(($farmer ?? $this->profile)->id, $this->from, $this->to);
    $this->incomeReport = fn($farmer = null) => app(IncomeAndExpenditureService::class)
        ->for(($farmer ?? $this->profile)->id, $this->from, $this->to);
    $this->trial = fn($farmer = null) => app(TrialBalanceService::class)
        ->for(($farmer ?? $this->profile)->id, $this->from, $this->to);

    // the same recipe ReportHeader signs with, written out so the exact keys and values are visible
    $this->expectedCode = function (string $title, $farmer, array $figures) {
        ksort($figures);
        $payload = implode('|', [$title, $farmer->uuid, $this->from, $this->to, 'confirmed', json_encode($figures)]);
        $hash = hash_hmac('sha256', $payload, config('app.report_secret'));

        return strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $hash), 0, 12));
    };
});

// --- account statement ---

test('statement: money in, money out, balance and the four class totals', function () {
    $s = ($this->statement)();

    // the totals count every row except the correction rows, so a cancelled record still counts here
    // and its amount is also reported on its own as "cancelled"; the balance nets the cancellations out
    expect([
        'in' => $s->totalInMinor,
        'out' => $s->totalOutMinor,
        'closing' => $s->closingBalanceMinor,
        'assets' => $s->totalAssetsMinor,
        'expenditure' => $s->totalExpenditureMinor,
        'income' => $s->totalIncomeMinor,
        'liability' => $s->totalLiabilityMinor,
        'cancelled' => $s->cancelledMinor,
    ])->toBe([
        'in' => 155000,
        'out' => 85000,
        'closing' => 50000,
        'assets' => 30000,
        'expenditure' => 55000,
        'income' => 155000,
        'liability' => 0,
        'cancelled' => 50000,
    ]);
});

test('statement: every row with its money, running balance, class tag and cancel state', function () {
    $rows = collect(($this->statement)()->rows)->map(fn($row) => [
        $row->templateName, $row->accountName, $row->moneyInMinor, $row->moneyOutMinor, $row->balanceMinor,
        $row->moneyClass?->value, $row->cancelState,
    ])->all();

    expect($rows)->toBe([
        ['Cash sale', 'Cash A/C', 100000, 0, 100000, 'income', 'open'],
        ['Feed expense', 'Cash A/C', 0, 40000, 60000, 'expenditure', 'open'],
        ['Animal purchase', 'Cash A/C', 0, 30000, 30000, 'asset', 'open'],
        ['Animal loss', null, 0, 0, 30000, null, 'open'],
        ['Cash sale', 'Accounts Receivable', 0, 0, 30000, null, 'open'],
        ['Payment received', 'Cash A/C', 20000, 0, 50000, 'income', 'open'],
        ['Cash sale', 'Cash A/C', 25000, 0, 75000, 'income', 'cancelled'],
        ['Feed expense', 'Cash A/C', 0, 15000, 60000, 'expenditure', 'cancelled'],
        ['Payment received', 'Cash A/C', 10000, 0, 70000, 'income', 'cancelled'],
        ['Correction', 'Cash A/C', 0, 25000, 45000, 'income', 'correction'],
        ['Correction', 'Cash A/C', 15000, 0, 60000, 'expenditure', 'correction'],
        // the correction of the cancelled payment carries the payment's class too (it used to carry none)
        ['Correction', 'Cash A/C', 0, 10000, 50000, 'income', 'correction'],
    ]);
});

test('statement: the loss row carries its value lost and no money', function () {
    $loss = collect(($this->statement)()->rows)->firstWhere('templateName', 'Animal loss');

    expect([$loss->moneyInMinor, $loss->moneyOutMinor, $loss->valueLostMinor])->toBe([0, 0, 12000]);
});

test('the clean farmer with nothing cancelled has the plain figures', function () {
    $s = ($this->statement)($this->clean);

    expect([
        'in' => $s->totalInMinor, 'out' => $s->totalOutMinor, 'closing' => $s->closingBalanceMinor,
        'assets' => $s->totalAssetsMinor, 'expenditure' => $s->totalExpenditureMinor,
        'income' => $s->totalIncomeMinor, 'liability' => $s->totalLiabilityMinor, 'cancelled' => $s->cancelledMinor,
    ])->toBe([
        'in' => 100000, 'out' => 70000, 'closing' => 30000,
        'assets' => 30000, 'expenditure' => 40000, 'income' => 100000, 'liability' => 0, 'cancelled' => 0,
    ]);
});

// the farmer on an unapproved unit: one live sale of 100 and one cancelled sale of 250, both provisional
test('held back: only the live provisional sale is held back on the statement and the trial balance (the cancelled one used to add 50000)', function () {
    $statement = ($this->statement)($this->provisional);
    $trial = ($this->trial)($this->provisional);

    expect([$statement->provisionalHeldBackMinor, $trial->provisionalHeldBackMinor])->toBe([10000, 10000]);
});

// --- income and expenditure ---

test('income and expenditure: income, expense, loss, net, cash collected and cash paid out', function () {
    $r = ($this->incomeReport)();

    // cancelled records count as zero. Expense includes the animal purchase (EXPENSE type, today's rule),
    // and income includes the whole credit sale (earned), while cash collected has only the 200 paid
    expect([
        'income' => $r->totalIncomeMinor,
        'expense' => $r->totalExpenseMinor,
        'loss' => $r->totalLossMinor,
        'net' => $r->netMinor,
        'cash_collected' => $r->cashCollectedMinor,
        'cash_paid_out' => $r->cashPaidOutMinor,
        'held_back' => $r->provisionalHeldBackMinor,
    ])->toBe([
        'income' => 150000,
        'expense' => 70000,
        'loss' => 12000,
        'net' => 68000,
        'cash_collected' => 120000,
        'cash_paid_out' => 70000,
        'held_back' => 0,
    ]);
});

// --- trial balance ---

test('trial balance: debit and credit per account, both sides of every cancellation included', function () {
    $t = ($this->trial)();

    $rows = collect($t->rows)->mapWithKeys(fn($row) => [$row->accountName => [$row->debitMinor, $row->creditMinor]])->sortKeys()->all();

    expect([
        'rows' => $rows,
        'debit' => $t->totalDebitMinor,
        'credit' => $t->totalCreditMinor,
        'balanced' => $t->isBalanced(),
    ])->toBe([
        'rows' => [
            'Accounts Receivable' => [60000, 30000],
            'Cash A/C' => [170000, 120000],
            'Feed A/C' => [55000, 15000],
            'Livestock A/C' => [30000, 12000],
            'Loss A/C' => [12000, 0],
            'Sales A/C' => [25000, 175000],
        ],
        'debit' => 352000,
        'credit' => 352000,
        'balanced' => true,
    ]);
});

// --- credit views ---

test('credit: the amount still owed per record, with the cancelled payment not counted', function () {
    $credit = app(CreditSettlementService::class);

    expect([
        'credit_sale_owed' => $credit->outstandingAmount($this->creditSale),
        'cancelled_sale_owed' => $credit->outstandingAmount($this->cancelledSale),
        'cash_sale_owed' => $credit->outstandingAmount(Transaction::where('is_credit', false)->first()),
    ])->toBe(['credit_sale_owed' => 30000, 'cancelled_sale_owed' => 0, 'cash_sale_owed' => 0]);
});

test('credit: cash collected counts the live payment only', function () {
    expect(($this->incomeReport)()->cashCollectedMinor)->toBe(120000);
});

// --- signed figures ---

test('signed figures: income and expenditure signs exactly income, expense and loss', function () {
    $header = ($this->incomeReport)()->header;

    expect($header->verificationCode)->toBe(($this->expectedCode)('Income and Expenditure', $this->profile, [
        'income' => 150000,
        'expense' => 70000,
        'loss' => 12000,
    ]));
});

test('signed figures: trial balance signs exactly debit and credit', function () {
    $header = ($this->trial)()->header;

    expect($header->verificationCode)->toBe(($this->expectedCode)('Trial Balance', $this->profile, [
        'debit' => 352000,
        'credit' => 352000,
    ]));
});

// the statement signs nine figures; pinned on the farmer with nothing cancelled, where every one is plain
test('signed figures: the statement signs exactly these nine figures', function () {
    $header = ($this->statement)($this->clean)->header;

    expect($header->verificationCode)->toBe(($this->expectedCode)('Account Statement', $this->clean, [
        'opening' => 0,
        'in' => 100000,
        'out' => 70000,
        'closing' => 30000,
        'page' => 1,
        'assets' => 30000,
        'expenditure' => 40000,
        'income' => 100000,
        'liability' => 0,
    ]));
});

// the signed class figures are the very numbers the page shows; with cancelled records in the books they
// used to include the correction rows (income 190000, expenditure 70000), which the page never showed
test('signed figures: with cancelled records, each signed value equals the displayed total', function () {
    $s = ($this->statement)();
    expect($s->header->verificationCode)->toBe(($this->expectedCode)('Account Statement', $this->profile, [
        'opening' => 0,
        // money in and out are the totals the page shows (they used to be signed over the correction rows too)
        'in' => $s->totalInMinor,
        'out' => $s->totalOutMinor,
        'closing' => $s->closingBalanceMinor,
        'page' => 1,
        'assets' => $s->totalAssetsMinor,
        'expenditure' => $s->totalExpenditureMinor,
        'income' => $s->totalIncomeMinor,
        'liability' => $s->totalLiabilityMinor,
    ]));
});

test('signed figures: the cancellation statement signs exactly these nine numbers', function () {
    $s = ($this->statement)();

    expect($s->header->verificationCode)->toBe(($this->expectedCode)('Account Statement', $this->profile, [
        'opening' => 0,
        // signed in 170000 and out 120000 before they matched the page
        'in' => 155000,
        'out' => 85000,
        'closing' => 50000,
        'page' => 1,
        'assets' => 30000,
        'expenditure' => 55000,
        'income' => 155000,
        'liability' => 0,
    ]));
});

test('signed figures: a statement with no cancellations signs the same values and code as before', function () {
    $s = ($this->statement)($this->clean);

    expect([
        'class_values_equal_displayed' => [30000, 40000, 100000, 0] === [$s->totalAssetsMinor, $s->totalExpenditureMinor, $s->totalIncomeMinor, $s->totalLiabilityMinor],
        'code_unchanged' => $s->header->verificationCode === ($this->expectedCode)('Account Statement', $this->clean, [
            'opening' => 0, 'in' => 100000, 'out' => 70000, 'closing' => 30000, 'page' => 1,
            'assets' => 30000, 'expenditure' => 40000, 'income' => 100000, 'liability' => 0,
        ]),
    ])->toBe(['class_values_equal_displayed' => true, 'code_unchanged' => true]);
});

test('signed figures: income and expenditure signs the same income, expense and loss it shows', function () {
    $r = ($this->incomeReport)();

    expect($r->header->verificationCode)->toBe(($this->expectedCode)('Income and Expenditure', $this->profile, [
        'income' => $r->totalIncomeMinor,
        'expense' => $r->totalExpenseMinor,
        'loss' => $r->totalLossMinor,
    ]));
});

test('signed figures: the trial balance signs the same debit and credit totals it shows', function () {
    $t = ($this->trial)();

    expect($t->header->verificationCode)->toBe(($this->expectedCode)('Trial Balance', $this->profile, [
        'debit' => $t->totalDebitMinor,
        'credit' => $t->totalCreditMinor,
    ]));
});

test('signed figures: the same figures give the same code, and one changed figure gives another', function () {
    $first = [
        ($this->statement)()->header->verificationCode,
        ($this->incomeReport)()->header->verificationCode,
        ($this->trial)()->header->verificationCode,
    ];
    $again = [
        ($this->statement)()->header->verificationCode,
        ($this->incomeReport)()->header->verificationCode,
        ($this->trial)()->header->verificationCode,
    ];

    ($this->put)($this->profile, $this->unit, $this->saleTemplate, '1');

    $after = [
        ($this->statement)()->header->verificationCode,
        ($this->incomeReport)()->header->verificationCode,
        ($this->trial)()->header->verificationCode,
    ];

    expect([
        'same' => $first === $again,
        'statement_changed' => $first[0] !== $after[0],
        'income_changed' => $first[1] !== $after[1],
        'trial_changed' => $first[2] !== $after[2],
    ])->toBe(['same' => true, 'statement_changed' => true, 'income_changed' => true, 'trial_changed' => true]);
});

// --- classes ---

test('only the four classes exist: asset, expenditure, income and liability', function () {
    expect(array_map(fn(MoneyClass $class) => $class->value, MoneyClass::cases()))
        ->toBe(['asset', 'expenditure', 'income', 'liability']);
});
