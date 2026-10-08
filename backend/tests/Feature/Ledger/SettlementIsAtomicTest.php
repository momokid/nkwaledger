<?php

// a credit settlement and its link row should be saved together, or not at all.

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
    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);

    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name,
        'control_id' => $control->id,
        'subcategory_id' => $sub,
        'type_id' => $type->id,
        'is_settlement' => $settlement,
    ]);

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $this->receivable = $account('Accounts Receivable', $assetSub->id, true);
    $account('Accounts Payable', $assetSub->id, true);
    $sales = $account('Sales A/C', $incomeSub->id);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->saleTemplate = TransactionTemplate::create([
        'name' => 'Crop Sale',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $sales->id,
        'settlement_side' => 'debit',
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

    $this->profile = FarmerProfile::factory()->create();
    $this->clerk = User::factory()->create();

    $this->creditSale = app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->saleTemplate->id,
        amount: '500',
        settlementAccountId: $this->receivable->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->clerk->id,
    ));
});

// the failing hook below is registered on the model class, so it must not outlive the test
afterEach(fn() => CreditSettlement::flushEventListeners());

test('control: a settlement that works leaves one transaction and one link row', function () {
    app(CreditSettlementService::class)->settle($this->creditSale, 20000, $this->cash->id, now()->toDateString(), $this->clerk->id);

    expect([
        'settlement_transactions' => Transaction::where('transaction_type', 'ADJUSTMENT')->count(),
        'link_rows' => CreditSettlement::count(),
    ])->toBe(['settlement_transactions' => 1, 'link_rows' => 1]);
});

test('a failed link row leaves no settlement transaction behind', function () {
    // the link row is the second write; make it fail
    CreditSettlement::creating(fn() => throw new RuntimeException('link row failed'));

    $failed = false;

    try {
        app(CreditSettlementService::class)->settle($this->creditSale, 20000, $this->cash->id, now()->toDateString(), $this->clerk->id);
    } catch (RuntimeException) {
        $failed = true;
    }

    $settlement = Transaction::where('transaction_type', 'ADJUSTMENT')->first();

    expect([
        'settle_threw' => $failed,
        'settlement_transactions' => Transaction::where('transaction_type', 'ADJUSTMENT')->count(),
        'journal_entries_for_it' => $settlement ? JournalEntry::where('transaction_id', $settlement->id)->count() : 0,
        'link_rows' => CreditSettlement::count(),
        'still_owed' => app(CreditSettlementService::class)->outstandingAmount($this->creditSale),
    ])->toBe([
        'settle_threw' => true,
        'settlement_transactions' => 0,
        'journal_entries_for_it' => 0,
        'link_rows' => 0,
        'still_owed' => 50000,
    ]);
});
