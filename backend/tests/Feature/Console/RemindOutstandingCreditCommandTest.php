<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Notification;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;

beforeEach(function () {
    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);

    $moneySub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Money']);
    $receivableSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Receivables']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);

    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $subcategoryId, bool $isSettlement = false) => LedgerAccount::create([
        'name' => $name,
        'control_id' => $control->id,
        'subcategory_id' => $subcategoryId,
        'type_id' => $type->id,
        'is_settlement' => $isSettlement,
    ]);

    $cash = $account('Cash A/C', $moneySub->id, true);
    $receivable = $account('Accounts Receivable', $receivableSub->id, true);
    $sales = $account('Income on Sales', $incomeSub->id);

    $creditSaleTemplate = TransactionTemplate::create([
        'name' => 'I sold crops',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $cash->id,
        'credit_account_id' => $sales->id,
        'settlement_side' => 'debit',
        'allows_credit' => true,
    ]);

    TransactionTemplate::create([
        'name' => 'Payment received',
        'slug' => 'payment_received',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $cash->id,
        'credit_account_id' => $receivable->id,
        'settlement_side' => 'debit',
    ]);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->farmer = FarmerProfile::factory()->create();
    $clerk = User::factory()->create();

    app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->farmer->id,
        transactionTemplateId: $creditSaleTemplate->id,
        amount: '500',
        settlementAccountId: $receivable->id,
        transactionDate: now()->subDays(4)->toDateString(),
        recordedBy: $clerk->id,
    ));
});

test('it reminds a farmer with a credit sale outstanding past three days', function () {
    $this->artisan('credit:remind')->assertExitCode(0);

    expect(Notification::where('user_id', $this->farmer->user_id)
        ->where('kind', 'credit.reminder')
        ->exists())->toBeTrue();
});

test('it reports how many reminders went out', function () {
    $this->artisan('credit:remind')
        ->expectsOutputToContain('1')
        ->assertExitCode(0);
});
