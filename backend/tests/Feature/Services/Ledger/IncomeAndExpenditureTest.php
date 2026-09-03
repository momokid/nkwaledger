<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\Reports\IncomeAndExpenditure;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;

beforeEach(function () {
    // reports refuse to build without a secret to sign them
    config(['app.report_secret' => 'testing-secret']);

    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);
    $expenses = LedgerCategory::create(['name' => 'Expenses', 'class_id' => $drClass->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Money']);
    $stockSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Farm Assets']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => $expenses->id, 'name' => 'Farm Expenses']);

    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = function (string $name, int $subcategoryId, bool $isSettlement = false) use ($control, $type) {
        return LedgerAccount::create([
            'name' => $name,
            'control_id' => $control->id,
            'subcategory_id' => $subcategoryId,
            'type_id' => $type->id,
            'is_settlement' => $isSettlement,
        ]);
    };

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $this->sales = $account('Income on Sales', $incomeSub->id);
    $this->otherIncome = $account('Other Income', $incomeSub->id);
    $this->feed = $account('Expense on Feed', $expenseSub->id);
    $this->labour = $account('Expense on Labour', $expenseSub->id);
    $this->livestock = $account('Livestock A/C', $stockSub->id);

    // a dead animal is value gone, not money paid, so it sits on its own line
    $this->lossAccount = $account('Loss on Farm Assets', $expenseSub->id);

    $this->saleTemplate = TransactionTemplate::create([
        'name' => 'I sold crops',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    $this->giftTemplate = TransactionTemplate::create([
        'name' => 'I received other income',
        'slug' => 'other_income',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->otherIncome->id,
        'settlement_side' => 'debit',
    ]);

    $this->feedTemplate = TransactionTemplate::create([
        'name' => 'I bought feed',
        'slug' => 'feed_purchase',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $this->feed->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
        'requires_farm_unit' => true,
    ]);

    $this->labourTemplate = TransactionTemplate::create([
        'name' => 'I paid for labour',
        'slug' => 'labour_cost',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $this->labour->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
    ]);

    $this->lossTemplate = TransactionTemplate::create([
        'name' => 'An animal died',
        'slug' => 'livestock_loss',
        'transaction_type' => 'LOSS',
        'debit_account_id' => $this->lossAccount->id,
        'credit_account_id' => $this->livestock->id,
        'settlement_side' => 'none',
        'requires_farm_unit' => true,
    ]);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->profile = FarmerProfile::factory()->create();
    $this->other = FarmerProfile::factory()->create();
    $this->staff = User::factory()->create();

    $this->approvedUnit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'approved_at' => now()->subMonths(6),
        'approved_by' => $this->staff->id,
    ]);

    $this->pendingUnit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'approved_at' => null,
    ]);

    // enough on record for any loss a test posts against it
    FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->approvedUnit->id,
        'opening_quantity' => 1000,
    ]);

    $posting = app(PostingService::class);

    $this->post = function (
        TransactionTemplate $template,
        string $amount,
        ?string $date = null,
        ?FarmUnit $unit = null,
        ?FarmerProfile $who = null,
    ) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: ($who ?? $this->profile)->id,
            transactionTemplateId: $template->id,
            amount: $amount,
            settlementAccountId: $template->settlement_side === 'none' ? null : $this->cash->id,
            transactionDate: $date ?? now()->toDateString(),
            farmUnitId: $template->requires_farm_unit ? ($unit ?? $this->approvedUnit)->id : null,
            recordedBy: $this->staff->id,
            quantityLost: $template->transaction_type === 'LOSS' ? '1' : null,
        ));
    };

    $this->service = app(IncomeAndExpenditureService::class);

    $this->run = function (array $overrides = []) {
        return $this->service->for(...array_merge([
            'farmerProfileId' => $this->profile->id,
            'from' => now()->startOfYear()->toDateString(),
            'to' => now()->endOfYear()->toDateString(),
            'includeProvisional' => false,
        ], $overrides));
    };
});

it('returns a report that knows its own scope', function () {
    ($this->post)($this->saleTemplate, '250');

    $report = ($this->run)();

    expect($report)->toBeInstanceOf(IncomeAndExpenditure::class);
    expect($report->farmerProfileId)->toBe($this->profile->id);
    expect($report->includeProvisional)->toBeFalse();
    expect($report->generatedAt)->not->toBeNull();
});

it('adds up what the farmer earned', function () {
    ($this->post)($this->saleTemplate, '250');
    ($this->post)($this->giftTemplate, '100');

    expect(($this->run)()->totalIncomeMinor)->toBe(35000);
});

it('adds up what the farmer paid out', function () {
    ($this->post)($this->feedTemplate, '300');
    ($this->post)($this->labourTemplate, '200');

    expect(($this->run)()->totalExpenseMinor)->toBe(50000);
});

// money paid out and value gone are different stories
it('keeps losses apart from expenses', function () {
    ($this->post)($this->feedTemplate, '300');
    ($this->post)($this->lossTemplate, '80');

    $report = ($this->run)();

    expect($report->totalExpenseMinor)->toBe(30000);
    expect($report->totalLossMinor)->toBe(8000);
});

it('shows what is left after everything', function () {
    ($this->post)($this->saleTemplate, '500');
    ($this->post)($this->feedTemplate, '200');
    ($this->post)($this->lossTemplate, '50');

    expect(($this->run)()->netMinor)->toBe(25000);
});

it('shows a shortfall as a negative figure', function () {
    ($this->post)($this->saleTemplate, '100');
    ($this->post)($this->feedTemplate, '300');

    expect(($this->run)()->netMinor)->toBe(-20000);
});

// one line per account, so the farmer can open a total up
it('lists each income account on its own line', function () {
    ($this->post)($this->saleTemplate, '250');
    ($this->post)($this->giftTemplate, '100');

    $report = ($this->run)();

    expect($report->incomeRows)->toHaveCount(2);
    expect($report->row($report->incomeRows, $this->sales->id)->amountMinor)->toBe(25000);
});

it('lists each expense account on its own line', function () {
    ($this->post)($this->feedTemplate, '300');
    ($this->post)($this->labourTemplate, '200');

    $report = ($this->run)();

    expect($report->expenseRows)->toHaveCount(2);
    expect($report->row($report->expenseRows, $this->labour->id)->amountMinor)->toBe(20000);
});

it('lists each loss account on its own line', function () {
    ($this->post)($this->lossTemplate, '80');

    $report = ($this->run)();

    expect($report->lossRows)->toHaveCount(1);
    expect($report->lossRows[0]->accountName)->toBe('Loss on Farm Assets');
});

// the screen folds the small lines up into groups
it('says which group each line belongs to', function () {
    ($this->post)($this->saleTemplate, '250');

    expect(($this->run)()->incomeRows[0]->groupName)->toBe('Farm Income');
});

it('adds the same account up across many records', function () {
    ($this->post)($this->feedTemplate, '100');
    ($this->post)($this->feedTemplate, '250.50');

    expect(($this->run)()->row(($this->run)()->expenseRows, $this->feed->id)->amountMinor)
        ->toBe(35050);
});

// a farmer with two records should not read a page of zeros
it('leaves out accounts the farmer never touched', function () {
    ($this->post)($this->saleTemplate, '250');

    expect(($this->run)()->expenseRows)->toBeEmpty();
});

// one farmer's books never show another farmer's money
it('reads only the farmer it was asked about', function () {
    ($this->post)($this->saleTemplate, '250');
    ($this->post)($this->saleTemplate, '900', null, null, $this->other);

    expect(($this->run)()->totalIncomeMinor)->toBe(25000);
});

it('reads only the dates it was asked about', function () {
    ($this->post)($this->saleTemplate, '250', now()->subMonths(3)->toDateString());
    ($this->post)($this->saleTemplate, '100', now()->toDateString());

    $report = ($this->run)([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect($report->totalIncomeMinor)->toBe(10000);
});

it('counts a record made on the last day of the range', function () {
    ($this->post)($this->saleTemplate, '250', now()->toDateString());

    $report = ($this->run)([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect($report->totalIncomeMinor)->toBe(25000);
});

// nothing waiting on approval reaches a report a bank will read
it('leaves out provisional records by default', function () {
    ($this->post)($this->feedTemplate, '300', null, $this->approvedUnit);
    ($this->post)($this->feedTemplate, '100', null, $this->pendingUnit);

    expect(($this->run)()->totalExpenseMinor)->toBe(30000);
});

it('includes provisional records when asked', function () {
    ($this->post)($this->feedTemplate, '300', null, $this->approvedUnit);
    ($this->post)($this->feedTemplate, '100', null, $this->pendingUnit);

    expect(($this->run)(['includeProvisional' => true])->totalExpenseMinor)->toBe(40000);
});

it('says how much was held back', function () {
    ($this->post)($this->feedTemplate, '300', null, $this->approvedUnit);
    ($this->post)($this->feedTemplate, '100', null, $this->pendingUnit);

    expect(($this->run)()->provisionalHeldBackMinor)->toBe(10000);
});

it('comes back empty for a farmer with no records', function () {
    $report = ($this->run)(['farmerProfileId' => $this->other->id]);

    expect($report->incomeRows)->toBeEmpty();
    expect($report->expenseRows)->toBeEmpty();
    expect($report->netMinor)->toBe(0);
});

it('reads the same twice in a row', function () {
    ($this->post)($this->saleTemplate, '250');
    ($this->post)($this->feedTemplate, '100');

    expect(($this->run)()->netMinor)->toBe(($this->run)()->netMinor);
});

// every printed report needs the same top and bottom
it('carries a header', function () {
    ($this->post)($this->saleTemplate, '250');

    $header = ($this->run)()->header;

    expect($header->title)->toBe('Income and Expenditure');
    expect($header->farmerReference)->toBe($this->profile->uuid);
    expect($header->verificationCode)->toMatch('/^[A-Z0-9]{12}$/');
});

it('signs the header with its own totals', function () {
    ($this->post)($this->saleTemplate, '250');

    $first = ($this->run)()->header->verificationCode;

    ($this->post)($this->feedTemplate, '100');

    expect(($this->run)()->header->verificationCode)->not->toBe($first);
});

// a loss and an expense of the same size must not sign the same
it('signs losses apart from expenses', function () {
    ($this->post)($this->feedTemplate, '100');

    $first = ($this->run)()->header->verificationCode;

    ($this->post)($this->lossTemplate, '100');

    expect(($this->run)()->header->verificationCode)->not->toBe($first);
});
