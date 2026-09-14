<?php

use App\Exceptions\Ledger\PostingFailed;
use App\Models\AccountingPeriod;
use App\Models\CreditSettlement;
use App\Models\FarmerProfile;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
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

    // paid someone to work: never a credit-eligible template at all
    $this->serviceTemplate = TransactionTemplate::create([
        'name' => 'I paid someone to work',
        'slug' => 'labour_cost',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $this->feed->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
        'allows_credit' => false,
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

    $this->period = AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->profile = FarmerProfile::factory()->create();
    $this->clerk = User::factory()->create();

    $this->posting = app(PostingService::class);
    $this->service = app(CreditSettlementService::class);

    $this->creditSale = $this->posting->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->creditSaleTemplate->id,
        amount: '500',
        settlementAccountId: $this->receivable->id,
        transactionDate: now()->toDateString(),
        narration: 'Sold maize on credit',
        recordedBy: $this->clerk->id,
    ));

    $this->creditPurchase = $this->posting->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->creditPurchaseTemplate->id,
        amount: '300',
        settlementAccountId: $this->payable->id,
        transactionDate: now()->toDateString(),
        narration: 'Bought feed on credit',
        recordedBy: $this->clerk->id,
    ));

    $this->cashSale = $this->posting->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->creditSaleTemplate->id,
        amount: '100',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        narration: 'Sold maize for cash',
        recordedBy: $this->clerk->id,
    ));

    $this->nonCreditExpense = $this->posting->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->serviceTemplate->id,
        amount: '50',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        narration: 'Paid for labour',
        recordedBy: $this->clerk->id,
    ));
});

it('settles a receivable by debiting the chosen account and crediting Accounts Receivable', function () {
    $settlement = $this->service->settle(
        original: $this->creditSale,
        amountMinor: 20000,
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->clerk->id,
    );

    expect($settlement)->toBeInstanceOf(Transaction::class);
    expect($settlement->transaction_template_id)->toBe(
        TransactionTemplate::where('slug', 'payment_received')->first()->id,
    );
    expect($settlement->amount_minor)->toBe(20000);

    $entry = JournalEntry::where('transaction_id', $settlement->id)->first();

    expect($entry->lines[0]->ledger_account_id)->toBe($this->cash->id);
    expect($entry->lines[0]->debit_minor)->toBe(20000);
    expect($entry->lines[1]->ledger_account_id)->toBe($this->receivable->id);
    expect($entry->lines[1]->credit_minor)->toBe(20000);
    $entry->assertBalanced();
});

it('settles a payable by crediting the chosen account and debiting Accounts Payable', function () {
    $settlement = $this->service->settle(
        original: $this->creditPurchase,
        amountMinor: 30000,
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->clerk->id,
    );

    expect($settlement->transaction_template_id)->toBe(
        TransactionTemplate::where('slug', 'payment_made')->first()->id,
    );

    $entry = JournalEntry::where('transaction_id', $settlement->id)->first();

    expect($entry->lines[0]->ledger_account_id)->toBe($this->payable->id);
    expect($entry->lines[0]->debit_minor)->toBe(30000);
    expect($entry->lines[1]->ledger_account_id)->toBe($this->cash->id);
    expect($entry->lines[1]->credit_minor)->toBe(30000);
});

it('records a credit_settlements row linking the original and the settlement transaction', function () {
    $settlement = $this->service->settle(
        original: $this->creditSale,
        amountMinor: 15000,
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->clerk->id,
    );

    $row = CreditSettlement::where('transaction_id', $this->creditSale->id)->first();

    expect($row)->not->toBeNull();
    expect($row->settlement_transaction_id)->toBe($settlement->id);
    expect($row->amount_minor)->toBe(15000);
});

it('allows a partial settlement, leaving the remainder outstanding', function () {
    $this->service->settle(
        original: $this->creditSale,
        amountMinor: 20000,
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->clerk->id,
    );

    expect($this->service->outstandingAmount($this->creditSale))->toBe(30000);
});

it('allows settling exactly the remaining balance across several payments', function () {
    $this->service->settle($this->creditSale, 20000, $this->cash->id, now()->toDateString(), $this->clerk->id);
    $this->service->settle($this->creditSale, 30000, $this->cash->id, now()->toDateString(), $this->clerk->id);

    expect($this->service->outstandingAmount($this->creditSale))->toBe(0);
});

it('rejects a settlement that would push the total above the original amount', function () {
    $this->service->settle($this->creditSale, 40000, $this->cash->id, now()->toDateString(), $this->clerk->id);

    expect(fn() => $this->service->settle(
        $this->creditSale, 20000, $this->cash->id, now()->toDateString(), $this->clerk->id,
    ))->toThrow(PostingFailed::class);

    expect(CreditSettlement::where('transaction_id', $this->creditSale->id)->count())->toBe(1);
});

it('rejects a single settlement larger than the original amount', function () {
    expect(fn() => $this->service->settle(
        $this->creditSale, 100000, $this->cash->id, now()->toDateString(), $this->clerk->id,
    ))->toThrow(PostingFailed::class);
});

it('rejects a transaction whose template never allows credit at all', function () {
    expect(fn() => $this->service->settle(
        $this->nonCreditExpense, 5000, $this->cash->id, now()->toDateString(), $this->clerk->id,
    ))->toThrow(PostingFailed::class);
});

it('rejects a transaction that allows credit but was actually settled in cash', function () {
    expect(fn() => $this->service->settle(
        $this->cashSale, 5000, $this->cash->id, now()->toDateString(), $this->clerk->id,
    ))->toThrow(PostingFailed::class);
});

it('reports the full amount outstanding before any settlement', function () {
    expect($this->service->outstandingAmount($this->creditSale))->toBe(50000);
    expect($this->service->outstandingAmount($this->creditPurchase))->toBe(30000);
});

it('reports zero outstanding for a transaction that was never on credit', function () {
    expect($this->service->outstandingAmount($this->cashSale))->toBe(0);
    expect($this->service->outstandingAmount($this->nonCreditExpense))->toBe(0);
});
