<?php

use App\Models\AccountingPeriod;
use App\Models\CreditReminder;
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
use App\Services\Ledger\CreditReminderService;
use App\Services\Ledger\CreditSettlementService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;

beforeEach(function () {
    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);
    $expenses = LedgerCategory::create(['name' => 'Expenses', 'class_id' => $drClass->id]);
    $liabilities = LedgerCategory::create(['name' => 'Liabilities', 'class_id' => $crClass->id]);

    $moneySub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Money']);
    $receivableSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Receivables']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => $expenses->id, 'name' => 'Farm Expenses']);
    $payableSub = LedgerSubcategory::create(['category_id' => $liabilities->id, 'name' => 'Payables']);

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

    $this->cash = $account('Cash A/C', $moneySub->id, true);
    $this->receivable = $account('Accounts Receivable', $receivableSub->id, true);
    $this->payable = $account('Accounts Payable', $payableSub->id, true);
    $this->sales = $account('Income on Sales', $incomeSub->id);
    $this->feed = $account('Expense on Feed', $expenseSub->id);

    $this->creditSaleTemplate = TransactionTemplate::create([
        'name' => 'I sold crops',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
        'allows_credit' => true,
    ]);

    $this->creditPurchaseTemplate = TransactionTemplate::create([
        'name' => 'I bought feed',
        'slug' => 'feed_purchase',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $this->feed->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
        'allows_credit' => true,
    ]);

    TransactionTemplate::create([
        'name' => 'Payment received',
        'slug' => 'payment_received',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->receivable->id,
        'settlement_side' => 'debit',
    ]);

    TransactionTemplate::create([
        'name' => 'Payment made',
        'slug' => 'payment_made',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->payable->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
    ]);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->farmer = FarmerProfile::factory()->create();
    $this->clerk = User::factory()->create();

    $this->posting = app(PostingService::class);
    $this->settlements = app(CreditSettlementService::class);
    $this->reminders = app(CreditReminderService::class);

    $this->postCreditSale = function (string $amount, string $date) {
        return $this->posting->post(new PostingRequest(
            farmerProfileId: $this->farmer->id,
            transactionTemplateId: $this->creditSaleTemplate->id,
            amount: $amount,
            settlementAccountId: $this->receivable->id,
            transactionDate: $date,
            recordedBy: $this->clerk->id,
        ));
    };

    $this->postCreditPurchase = function (string $amount, string $date) {
        return $this->posting->post(new PostingRequest(
            farmerProfileId: $this->farmer->id,
            transactionTemplateId: $this->creditPurchaseTemplate->id,
            amount: $amount,
            settlementAccountId: $this->payable->id,
            transactionDate: $date,
            recordedBy: $this->clerk->id,
        ));
    };
});

test('a credit sale still outstanding after three days gets a reminder', function () {
    $sale = ($this->postCreditSale)('500', now()->subDays(3)->toDateString());

    $sent = $this->reminders->sendDue();

    expect($sent)->toBe(1);
    expect(Notification::where('user_id', $this->farmer->user_id)->where('kind', 'credit.reminder')->exists())->toBeTrue();
    expect(CreditReminder::where('transaction_id', $sale->id)->exists())->toBeTrue();
});

test('a credit purchase still outstanding after three days gets a reminder too', function () {
    ($this->postCreditPurchase)('300', now()->subDays(3)->toDateString());

    expect($this->reminders->sendDue())->toBe(1);
});

test('nothing is due before three days have passed', function () {
    ($this->postCreditSale)('500', now()->subDays(2)->toDateString());

    expect($this->reminders->sendDue())->toBe(0);
    expect(Notification::where('kind', 'credit.reminder')->exists())->toBeFalse();
});

test('a fully settled credit sale is never reminded', function () {
    $sale = ($this->postCreditSale)('500', now()->subDays(5)->toDateString());

    $this->settlements->settle(
        original: $sale,
        amountMinor: 50000,
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->clerk->id,
    );

    expect($this->reminders->sendDue())->toBe(0);
});

test('a partially settled sale is still reminded, for the remaining amount', function () {
    $sale = ($this->postCreditSale)('500', now()->subDays(5)->toDateString());

    $this->settlements->settle(
        original: $sale,
        amountMinor: 20000,
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->clerk->id,
    );

    expect($this->reminders->sendDue())->toBe(1);

    $notification = Notification::where('kind', 'credit.reminder')->first();
    expect($notification->message)->toContain('GHS 300.00');
});

test('a non-credit transaction is never reminded', function () {
    $this->posting->post(new PostingRequest(
        farmerProfileId: $this->farmer->id,
        transactionTemplateId: $this->creditSaleTemplate->id,
        amount: '500',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->subDays(5)->toDateString(),
        recordedBy: $this->clerk->id,
    ));

    expect($this->reminders->sendDue())->toBe(0);
});

test('the same outstanding sale is not reminded twice in one run', function () {
    ($this->postCreditSale)('500', now()->subDays(5)->toDateString());

    $this->reminders->sendDue();

    expect($this->reminders->sendDue())->toBe(0);
});

test('no second reminder goes out before three more days have passed', function () {
    $sale = ($this->postCreditSale)('500', now()->subDays(5)->toDateString());
    $this->reminders->sendDue();

    CreditReminder::where('transaction_id', $sale->id)->update(['created_at' => now()->subDays(1)]);

    expect($this->reminders->sendDue())->toBe(0);
});

test('a second reminder goes out once three more days have passed since the last one', function () {
    $sale = ($this->postCreditSale)('500', now()->subDays(8)->toDateString());
    $this->reminders->sendDue();

    CreditReminder::where('transaction_id', $sale->id)->update(['created_at' => now()->subDays(3)]);

    expect($this->reminders->sendDue())->toBe(1);
    expect(CreditReminder::where('transaction_id', $sale->id)->count())->toBe(2);
});

test('the message names the amount owed, what it was for, and when it happened', function () {
    ($this->postCreditPurchase)('300', now()->subDays(3)->toDateString());

    $this->reminders->sendDue();

    $notification = Notification::where('kind', 'credit.reminder')->first();

    expect($notification->message)->toBe(
        'You still owe GHS 300.00 for I bought feed, recorded on ' . now()->subDays(3)->format('d M Y') . '.',
    );
});
