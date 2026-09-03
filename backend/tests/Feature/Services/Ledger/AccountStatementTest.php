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
use App\Services\Ledger\Reports\AccountStatement;
use App\Services\Ledger\Reports\AccountStatementService;

beforeEach(function () {
    // reports refuse to build without a secret to sign them
    config(['app.report_secret' => 'testing-secret']);

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

    $account = function (string $name, int $subcategoryId, bool $isSettlement = false) use ($control, $type) {
        return LedgerAccount::create([
            'name' => $name,
            'control_id' => $control->id,
            'subcategory_id' => $subcategoryId,
            'type_id' => $type->id,
            // only a ticked account counts as money the farmer holds
            'is_settlement' => $isSettlement,
        ]);
    };

    $this->cash = $account('Cash on Hand', $assetSub->id, true);
    $this->momo = $account('Mobile Money', $assetSub->id, true);
    $this->sales = $account('Crop Sales', $incomeSub->id);
    $this->feed = $account('Feed Expense', $expenseSub->id);
    $this->livestock = $account('Livestock', $assetSub->id);
    $this->lossOnStock = $account('Loss on Livestock', $incomeSub->id);

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

    // an animal dying moves no money at all
    $this->lossTemplate = TransactionTemplate::create([
        'name' => 'An animal died',
        'slug' => 'livestock_loss',
        'transaction_type' => 'LOSS',
        'debit_account_id' => $this->lossOnStock->id,
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

    $posting = app(PostingService::class);

    $this->sell = function (string $amount, ?string $date = null, ?LedgerAccount $into = null, ?FarmerProfile $who = null) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: ($who ?? $this->profile)->id,
            transactionTemplateId: $this->saleTemplate->id,
            amount: $amount,
            settlementAccountId: ($into ?? $this->cash)->id,
            transactionDate: $date ?? now()->toDateString(),
            narration: 'Sold maize',
            recordedBy: $this->staff->id,
        ));
    };

    $this->spend = function (string $amount, ?string $date = null, ?FarmUnit $unit = null) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $this->profile->id,
            transactionTemplateId: $this->feedTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date ?? now()->toDateString(),
            farmUnitId: ($unit ?? $this->approvedUnit)->id,
            recordedBy: $this->staff->id,
        ));
    };

    $this->lose = function (string $amount, ?string $date = null) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $this->profile->id,
            transactionTemplateId: $this->lossTemplate->id,
            amount: $amount,
            settlementAccountId: null,
            transactionDate: $date ?? now()->toDateString(),
            farmUnitId: $this->approvedUnit->id,
            recordedBy: $this->staff->id,
        ));
    };

    $this->approver = User::factory()->create();

    TransactionTemplate::create([
        'name' => 'Correction of an earlier record',
        'slug' => 'correction',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'none',
    ]);

    $this->service = app(AccountStatementService::class);

    $this->run = function (array $overrides = []) {
        return $this->service->for(...array_merge([
            'farmerProfileId' => $this->profile->id,
            'from' => now()->startOfYear()->toDateString(),
            'to' => now()->endOfYear()->toDateString(),
            'includeProvisional' => false,
            'accountId' => null,
            'page' => 1,
            'perPage' => 50,
        ], $overrides));
    };
});

it('returns a statement that knows its own scope', function () {
    ($this->sell)('250');

    $statement = ($this->run)();

    expect($statement)->toBeInstanceOf(AccountStatement::class);
    expect($statement->farmerProfileId)->toBe($this->profile->id);
    expect($statement->includeProvisional)->toBeFalse();
    expect($statement->generatedAt)->not->toBeNull();
});

it('lists one row for each transaction', function () {
    ($this->sell)('250');
    ($this->spend)('100');

    expect(($this->run)()->rows)->toHaveCount(2);
});

// money coming in and money going out, never debit and credit
it('shows money in on a sale', function () {
    ($this->sell)('250');

    $row = ($this->run)()->rows[0];

    expect($row->moneyInMinor)->toBe(25000);
    expect($row->moneyOutMinor)->toBe(0);
});

it('shows money out on a purchase', function () {
    ($this->spend)('100');

    $row = ($this->run)()->rows[0];

    expect($row->moneyInMinor)->toBe(0);
    expect($row->moneyOutMinor)->toBe(10000);
});

// an animal dying is a real event with no money attached
it('shows a loss with no money moving', function () {
    ($this->lose)('80');

    $row = ($this->run)()->rows[0];

    expect($row->moneyInMinor)->toBe(0);
    expect($row->moneyOutMinor)->toBe(0);
    expect($row->balanceMinor)->toBe(0);
});

it('carries the reference a farmer can read out', function () {
    $transaction = ($this->sell)('250');

    expect(($this->run)()->rows[0]->reference)->toBe($transaction->reference);
});

// the row has to say what happened in words the farmer used or chose
it('says what happened in plain words', function () {
    ($this->sell)('250');

    $row = ($this->run)()->rows[0];

    expect($row->description)->toBe('Sold maize');
    expect($row->templateName)->toBe('I sold crops');
});

it('falls back to the template name when nothing was written', function () {
    app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->saleTemplate->id,
        amount: '250',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->staff->id,
    ));

    expect(($this->run)()->rows[0]->description)->toBe('I sold crops');
});

it('reads oldest first, so the balance runs forward', function () {
    ($this->sell)('100', now()->subDays(5)->toDateString());
    ($this->sell)('200', now()->subDays(2)->toDateString());

    $rows = ($this->run)()->rows;

    expect($rows[0]->moneyInMinor)->toBe(10000);
    expect($rows[1]->moneyInMinor)->toBe(20000);
});

// the number a farmer checks against their own pocket
it('runs the balance forward down the page', function () {
    ($this->sell)('250', now()->subDays(5)->toDateString());
    ($this->spend)('100', now()->subDays(3)->toDateString());
    ($this->sell)('50', now()->subDays(1)->toDateString());

    $rows = ($this->run)()->rows;

    expect($rows[0]->balanceMinor)->toBe(25000);
    expect($rows[1]->balanceMinor)->toBe(15000);
    expect($rows[2]->balanceMinor)->toBe(20000);
});

// cash and momo are both money the farmer has
it('counts every settlement account in one balance', function () {
    ($this->sell)('250', now()->subDays(2)->toDateString(), $this->cash);
    ($this->sell)('100', now()->subDay()->toDateString(), $this->momo);

    expect(($this->run)()->rows[1]->balanceMinor)->toBe(35000);
});

// a statement starting in March has to know what February left behind
it('opens with what was there before the range began', function () {
    ($this->sell)('250', now()->subMonths(3)->toDateString());
    ($this->sell)('100', now()->toDateString());

    $statement = ($this->run)([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect($statement->openingBalanceMinor)->toBe(25000);
    expect($statement->rows[0]->balanceMinor)->toBe(35000);
});

it('opens at zero when nothing came before', function () {
    ($this->sell)('250');

    expect(($this->run)()->openingBalanceMinor)->toBe(0);
});

it('closes on the last balance of the page', function () {
    ($this->sell)('250', now()->subDays(2)->toDateString());
    ($this->spend)('100', now()->subDay()->toDateString());

    expect(($this->run)()->closingBalanceMinor)->toBe(15000);
});

it('adds up the money in and the money out', function () {
    ($this->sell)('250');
    ($this->sell)('100');
    ($this->spend)('75');

    $statement = ($this->run)();

    expect($statement->totalInMinor)->toBe(35000);
    expect($statement->totalOutMinor)->toBe(7500);
});

// one farmer's books never show another farmer's money
it('reads only the farmer it was asked about', function () {
    ($this->sell)('250');
    ($this->sell)('900', null, null, $this->other);

    expect(($this->run)()->rows)->toHaveCount(1);
});

it('reads only the dates it was asked about', function () {
    ($this->sell)('250', now()->subMonths(3)->toDateString());
    ($this->sell)('100', now()->toDateString());

    $statement = ($this->run)([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect($statement->rows)->toHaveCount(1);
});

it('counts a record made on the last day of the range', function () {
    ($this->sell)('250', now()->toDateString());

    $statement = ($this->run)([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect($statement->rows)->toHaveCount(1);
});

// nothing waiting on approval reaches a report a bank will read
it('leaves out provisional records by default', function () {
    ($this->spend)('100', null, $this->approvedUnit);
    ($this->spend)('50', null, $this->pendingUnit);

    expect(($this->run)()->rows)->toHaveCount(1);
});

it('includes provisional records when asked', function () {
    ($this->spend)('100', null, $this->approvedUnit);
    ($this->spend)('50', null, $this->pendingUnit);

    expect(($this->run)(['includeProvisional' => true])->rows)->toHaveCount(2);
});

// the farmer needs to know which rows are still waiting
it('flags a provisional row when one is shown', function () {
    ($this->spend)('50', null, $this->pendingUnit);

    expect(($this->run)(['includeProvisional' => true])->rows[0]->isProvisional)->toBeTrue();
});

// a bank sometimes wants one account on its own
it('narrows to a single account when asked', function () {
    ($this->sell)('250', now()->subDays(2)->toDateString(), $this->cash);
    ($this->sell)('100', now()->subDay()->toDateString(), $this->momo);

    $statement = ($this->run)(['accountId' => $this->momo->id]);

    expect($statement->rows)->toHaveCount(1);
    expect($statement->rows[0]->moneyInMinor)->toBe(10000);
});

it('breaks a long statement into pages', function () {
    foreach (range(1, 5) as $day) {
        ($this->sell)('100', now()->subDays(10 - $day)->toDateString());
    }

    $statement = ($this->run)(['perPage' => 2]);

    expect($statement->rows)->toHaveCount(2);
    expect($statement->total)->toBe(5);
    expect($statement->lastPage)->toBe(3);
});

// a running balance that restarts on page two is a lie
it('carries the balance onto the next page', function () {
    foreach (range(1, 5) as $day) {
        ($this->sell)('100', now()->subDays(10 - $day)->toDateString());
    }

    $second = ($this->run)(['perPage' => 2, 'page' => 2]);

    expect($second->openingBalanceMinor)->toBe(20000);
    expect($second->rows[0]->balanceMinor)->toBe(30000);
});

it('comes back empty for a farmer with no records', function () {
    $statement = ($this->run)(['farmerProfileId' => $this->other->id]);

    expect($statement->rows)->toBeEmpty();
    expect($statement->openingBalanceMinor)->toBe(0);
    expect($statement->closingBalanceMinor)->toBe(0);
});

it('reads the same twice in a row', function () {
    ($this->sell)('250');
    ($this->spend)('100');

    expect(($this->run)()->closingBalanceMinor)->toBe(($this->run)()->closingBalanceMinor);
});

// every printed report needs the same top and bottom
it('carries a header', function () {
    ($this->sell)('250');

    $header = ($this->run)()->header;

    expect($header->title)->toBe('Account Statement');
    expect($header->farmerReference)->toBe($this->profile->uuid);
    expect($header->verificationCode)->toMatch('/^[A-Z0-9]{12}$/');
});

it('signs the header with its own figures', function () {
    ($this->sell)('250');

    $first = ($this->run)()->header->verificationCode;

    ($this->sell)('100');

    expect(($this->run)()->header->verificationCode)->not->toBe($first);
});

// two pages of one statement are two different documents
it('signs each page differently', function () {
    foreach (range(1, 5) as $day) {
        ($this->sell)('100', now()->subDays(10 - $day)->toDateString());
    }

    $first = ($this->run)(['perPage' => 2, 'page' => 1])->header->verificationCode;
    $second = ($this->run)(['perPage' => 2, 'page' => 2])->header->verificationCode;

    expect($second)->not->toBe($first);
});
// a cancellation is money moving back, not money earned or spent
it('keeps a cancellation out of the money in total', function () {
    $purchase = ($this->spend)('100');

    cancel($purchase, $this->staff, $this->approver);

    $statement = ($this->run)();

    expect($statement->totalInMinor)->toBe(0);
    expect($statement->totalOutMinor)->toBe(10000);
});

it('counts what was cancelled on its own', function () {
    $purchase = ($this->spend)('100');

    cancel($purchase, $this->staff, $this->approver);

    expect(($this->run)()->cancelledMinor)->toBe(10000);
});

it('still leaves the farmer with the right balance', function () {
    ($this->sell)('250');
    $purchase = ($this->spend)('100');

    cancel($purchase, $this->staff, $this->approver);

    expect(($this->run)()->closingBalanceMinor)->toBe(25000);
});

it('counts a cancelled sale on its own too', function () {
    $sale = ($this->sell)('250');

    cancel($sale, $this->staff, $this->approver);

    $statement = ($this->run)();

    expect($statement->totalInMinor)->toBe(25000);
    expect($statement->totalOutMinor)->toBe(0);
    expect($statement->cancelledMinor)->toBe(25000);
    expect($statement->closingBalanceMinor)->toBe(0);
});

it('counts nothing cancelled when nothing was', function () {
    ($this->sell)('250');

    expect(($this->run)()->cancelledMinor)->toBe(0);
});

function cancel(App\Models\Transaction $record, App\Models\User $asker, App\Models\User $approver): void
{
    $service = app(App\Services\Ledger\ReversalService::class);

    $service->approve($service->request($record, $asker, 'Wrong amount typed.'), $approver);
}
