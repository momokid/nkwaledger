<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
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
use App\Services\Ledger\Reports\TrialBalance;
use App\Services\Ledger\Reports\TrialBalanceService;

beforeEach(function () {
    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);
    $expenses = LedgerCategory::create(['name' => 'Expenses', 'class_id' => $drClass->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Short Term Asset']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => $expenses->id, 'name' => 'Farm Expense']);

    $control = LedgerControl::create(['name' => 'Cash Ctrl']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = function (string $name, int $subcategoryId) use ($control, $type) {
        return LedgerAccount::create([
            'name' => $name,
            'control_id' => $control->id,
            'subcategory_id' => $subcategoryId,
            'type_id' => $type->id,
        ]);
    };

    $this->cash = $account('Cash on Hand', $assetSub->id);
    $this->momo = $account('Mobile Money', $assetSub->id);
    $this->sales = $account('Crop Sales', $incomeSub->id);
    $this->feed = $account('Feed Expense', $expenseSub->id);
    $this->untouched = $account('Bank Account', $assetSub->id);

    $this->saleTemplate = TransactionTemplate::create([
        'name' => 'I sold crops',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
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
        'approved_at' => now()->subMonth(),
        'approved_by' => $this->staff->id,
    ]);

    $this->pendingUnit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'approved_at' => null,
    ]);

    $posting = app(PostingService::class);

    $this->sell = function (string $amount, ?string $date = null, ?FarmerProfile $who = null) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: ($who ?? $this->profile)->id,
            transactionTemplateId: $this->saleTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date ?? now()->toDateString(),
            recordedBy: $this->staff->id,
        ));
    };

    $this->buyFeed = function (string $amount, ?FarmUnit $unit = null) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $this->profile->id,
            transactionTemplateId: $this->feedTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: now()->toDateString(),
            farmUnitId: ($unit ?? $this->approvedUnit)->id,
            recordedBy: $this->staff->id,
        ));
    };

    $this->service = app(TrialBalanceService::class);

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
    ($this->sell)('250');

    $report = ($this->run)();

    expect($report)->toBeInstanceOf(TrialBalance::class);
    expect($report->farmerProfileId)->toBe($this->profile->id);
    expect($report->includeProvisional)->toBeFalse();
    expect($report->generatedAt)->not->toBeNull();
});

// the whole product rests on this one line
it('always balances', function () {
    ($this->sell)('250');
    ($this->sell)('100.50');
    ($this->buyFeed)('75.25');

    $report = ($this->run)();

    expect($report->totalDebitMinor)->toBe($report->totalCreditMinor);
    expect($report->isBalanced())->toBeTrue();
});

it('adds up each account', function () {
    ($this->sell)('250');
    ($this->buyFeed)('100');

    $report = ($this->run)();

    $cash = $report->row($this->cash->id);

    expect($cash->debitMinor)->toBe(25000);
    expect($cash->creditMinor)->toBe(10000);
});

// an asset holds a debit balance, so money in less money out reads as a positive figure
it('shows a debit account with what is left on the debit side', function () {
    ($this->sell)('250');
    ($this->buyFeed)('100');

    $row = ($this->run)()->row($this->cash->id);

    expect($row->class)->toBe('Dr');
    expect($row->balanceMinor)->toBe(15000);
});

it('shows a credit account with what is left on the credit side', function () {
    ($this->sell)('250');

    $row = ($this->run)()->row($this->sales->id);

    expect($row->class)->toBe('Cr');
    expect($row->balanceMinor)->toBe(25000);
});

// a farmer with two records should not read a page of zeros
it('leaves out accounts the farmer never touched', function () {
    ($this->sell)('250');

    $report = ($this->run)();

    expect($report->row($this->untouched->id))->toBeNull();
    expect($report->rows)->toHaveCount(2);
});

it('names every account it lists', function () {
    ($this->sell)('250');

    expect(($this->run)()->row($this->cash->id)->accountName)->toBe('Cash on Hand');
});

// one farmer's books never show another farmer's money
it('reads only the farmer it was asked about', function () {
    ($this->sell)('250');
    ($this->sell)('900', null, $this->other);

    $report = ($this->run)();

    expect($report->row($this->cash->id)->debitMinor)->toBe(25000);
});

it('reads only the dates it was asked about', function () {
    ($this->sell)('250', now()->subMonths(2)->toDateString());
    ($this->sell)('100', now()->toDateString());

    $report = ($this->run)([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect($report->row($this->cash->id)->debitMinor)->toBe(10000);
});

it('counts a record made on the first and last day of the range', function () {
    ($this->sell)('250', now()->startOfMonth()->toDateString());
    ($this->sell)('100', now()->toDateString());

    $report = ($this->run)([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect($report->row($this->cash->id)->debitMinor)->toBe(35000);
});

// nothing waiting on approval reaches a report a bank will read
it('leaves out provisional records by default', function () {
    ($this->buyFeed)('100', $this->approvedUnit);
    ($this->buyFeed)('50', $this->pendingUnit);

    $report = ($this->run)();

    expect($report->row($this->feed->id)->debitMinor)->toBe(10000);
});

// the farmer sees their own records, all of them
it('includes provisional records when asked', function () {
    ($this->buyFeed)('100', $this->approvedUnit);
    ($this->buyFeed)('50', $this->pendingUnit);

    $report = ($this->run)(['includeProvisional' => true]);

    expect($report->row($this->feed->id)->debitMinor)->toBe(15000);
});

// dropping half a transaction would unbalance the books, so both legs go together
it('still balances with provisional records left out', function () {
    ($this->sell)('250');
    ($this->buyFeed)('50', $this->pendingUnit);

    $report = ($this->run)();

    expect($report->isBalanced())->toBeTrue();
});

// a farmer looking at a smaller number deserves to know why
it('says how much was held back', function () {
    ($this->buyFeed)('100', $this->approvedUnit);
    ($this->buyFeed)('50', $this->pendingUnit);

    $report = ($this->run)();

    expect($report->provisionalHeldBackMinor)->toBe(5000);
});

it('holds nothing back when provisional records are included', function () {
    ($this->buyFeed)('50', $this->pendingUnit);

    $report = ($this->run)(['includeProvisional' => true]);

    expect($report->provisionalHeldBackMinor)->toBe(0);
});

it('comes back empty for a farmer with no records', function () {
    $report = ($this->run)(['farmerProfileId' => $this->other->id]);

    expect($report->rows)->toBeEmpty();
    expect($report->totalDebitMinor)->toBe(0);
    expect($report->isBalanced())->toBeTrue();
});

it('reads the same twice in a row', function () {
    ($this->sell)('250');
    ($this->buyFeed)('100');

    expect(($this->run)()->totalDebitMinor)->toBe(($this->run)()->totalDebitMinor);
});
