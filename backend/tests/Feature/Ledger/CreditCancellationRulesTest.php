<?php

// cancelling around credit records: a payment can be cancelled, a record with live payments cannot,
// payments come off newest first, and a cancelled record can no longer be paid.

use App\Exceptions\Ledger\PostingFailed;
use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
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
use App\Services\Ledger\Reports\AccountStatementService;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use App\Services\Ledger\ReversalService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

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

    TransactionTemplate::create([
        'name' => 'Correction',
        'slug' => 'correction',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $sales->id,
        'settlement_side' => 'none',
    ]);

    $this->otherAdjustment = TransactionTemplate::create([
        'name' => 'Some other adjustment',
        'slug' => 'other_adjustment',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $sales->id,
        'settlement_side' => 'none',
    ]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
    $this->clerk = User::factory()->create();
    $this->approver = User::factory()->create();
    $this->settlements = app(CreditSettlementService::class);

    $this->creditSale = app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->saleTemplate->id,
        amount: '500',
        settlementAccountId: $this->receivable->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->clerk->id,
    ));

    $this->pay = fn(int $minor = 20000) => $this->settlements->settle($this->creditSale, $minor, $this->cash->id, now()->toDateString(), $this->clerk->id);

    $this->cancel = function (Transaction $transaction) {
        $service = app(ReversalService::class);
        $request = $service->request($transaction, $this->clerk, 'Typed wrongly');
        $service->approve($request, $this->approver);
    };

    // the refusal message, or null when the cancel went through
    $this->tryCancel = function (Transaction $transaction) {
        try {
            ($this->cancel)($transaction);
        } catch (PostingFailed $failure) {
            return $failure->getMessage();
        }

        return null;
    };

    $this->from = now()->subDays(6)->toDateString();
    $this->to = now()->toDateString();
});

test('a credit payment can be cancelled, and the amount owed goes back up', function () {
    $payment = ($this->pay)(20000);
    $owedAfterPayment = $this->settlements->outstandingAmount($this->creditSale);

    $refused = ($this->tryCancel)($payment);

    expect([
        'refused' => $refused,
        'owed_after_payment' => $owedAfterPayment,
        'owed_after_cancel' => $this->settlements->outstandingAmount($this->creditSale),
    ])->toBe(['refused' => null, 'owed_after_payment' => 30000, 'owed_after_cancel' => 50000]);
});

test('a cancelled credit payment is left out of cash collected', function () {
    $payment = ($this->pay)(20000);
    $before = app(IncomeAndExpenditureService::class)->for($this->profile->id, $this->from, $this->to)->cashCollectedMinor;

    ($this->cancel)($payment);

    expect([
        'before' => $before,
        'after' => app(IncomeAndExpenditureService::class)->for($this->profile->id, $this->from, $this->to)->cashCollectedMinor,
    ])->toBe(['before' => 20000, 'after' => 0]);
});

test('a cancelled credit payment nets out of the statement and shows as cancelled', function () {
    $payment = ($this->pay)(20000);

    ($this->cancel)($payment);

    $statement = app(AccountStatementService::class)->for($this->profile->id, $this->from, $this->to);
    $states = collect($statement->rows)->pluck('cancelState', 'reference');

    expect([
        'closing' => $statement->closingBalanceMinor,
        'cancelled' => $statement->cancelledMinor,
        'payment_state' => $states[$payment->reference],
    ])->toBe(['closing' => 0, 'cancelled' => 20000, 'payment_state' => 'cancelled']);
});

test('a cancelled credit payment is not counted on the credit tab', function () {
    ($this->cancel)(($this->pay)(20000));

    $this->actingAs($this->farmerUser)->get('/my-records')
        ->assertInertia(fn($page) => $page
            ->has('creditRows', 1)
            ->where('creditRows.0.outstanding', 50000));
});

test('a correction itself still cannot be cancelled', function () {
    ($this->cancel)($this->creditSale);
    $correction = Transaction::where('reverses_transaction_id', $this->creditSale->id)->first();

    expect(($this->tryCancel)($correction))->toBe('A correction cannot itself be cancelled.');
});

test('any other adjustment still cannot be cancelled', function () {
    $adjustment = app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->otherAdjustment->id,
        amount: '10',
        settlementAccountId: null,
        transactionDate: now()->toDateString(),
        recordedBy: $this->clerk->id,
    ));

    expect(($this->tryCancel)($adjustment))->toBe('A correction cannot itself be cancelled.');
});

test('a cancelled credit record cannot be settled', function () {
    ($this->cancel)($this->creditSale);

    $refusal = null;

    try {
        ($this->pay)(20000);
    } catch (PostingFailed $failure) {
        $refusal = $failure->getMessage();
    }

    expect([$refusal, Transaction::where('transaction_type', 'ADJUSTMENT')->count()])
        ->toBe(['That record has been cancelled, so it cannot be paid.', 1]);
});

test('a cancelled credit record owes nothing and leaves the credit tab', function () {
    ($this->cancel)($this->creditSale);

    $this->actingAs($this->farmerUser)->get('/my-records')
        ->assertInertia(fn($page) => $page->has('creditRows', 0));

    expect($this->settlements->outstandingAmount($this->creditSale))->toBe(0);
});

test('a credit record with a live payment cannot be cancelled until the payment is', function () {
    $payment = ($this->pay)(20000);

    $whileLive = ($this->tryCancel)($this->creditSale);
    ($this->cancel)($payment);
    $afterPaymentCancelled = ($this->tryCancel)($this->creditSale);

    expect([
        'while_live' => $whileLive,
        'after_payment_cancelled' => $afterPaymentCancelled,
        'record_cancelled' => $this->creditSale->reversedBy()->exists(),
    ])->toBe([
        'while_live' => 'Please cancel the payments on this record first, newest first.',
        'after_payment_cancelled' => null,
        'record_cancelled' => true,
    ]);
});

test('payments are cancelled newest first', function () {
    $older = ($this->pay)(10000);
    $newer = ($this->pay)(15000);

    $olderFirst = ($this->tryCancel)($older);
    $newerOk = ($this->tryCancel)($newer);
    $olderOk = ($this->tryCancel)($older);

    expect([
        'older_first' => $olderFirst,
        'newer' => $newerOk,
        'older_after' => $olderOk,
        'owed' => $this->settlements->outstandingAmount($this->creditSale),
    ])->toBe([
        'older_first' => 'Please cancel the newer payments on this record first.',
        'newer' => null,
        'older_after' => null,
        'owed' => 50000,
    ]);
});

test('a payment cancelled and then paid again can still clear the whole debt', function () {
    $payment = ($this->pay)(20000);
    ($this->cancel)($payment);

    ($this->pay)(50000);

    expect($this->settlements->outstandingAmount($this->creditSale))->toBe(0);
});
