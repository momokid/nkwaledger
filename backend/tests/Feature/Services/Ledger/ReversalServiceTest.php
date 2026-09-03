<?php

use App\Exceptions\Ledger\PostingFailed;
use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\ReversalRequest;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\ReversalService;

beforeEach(function () {
    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);
    $expenses = LedgerCategory::create(['name' => 'Expenses', 'class_id' => $drClass->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Money']);
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
    $this->feed = $account('Expense on Feed', $expenseSub->id);

    $this->saleTemplate = TransactionTemplate::create([
        'name' => 'I sold crops',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    $this->adjustmentTemplate = TransactionTemplate::create([
        'name' => 'A correction',
        'slug' => 'correction',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->sales->id,
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
    $this->manager = User::factory()->create();

    $posting = app(PostingService::class);

    $this->original = $posting->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->saleTemplate->id,
        amount: '250',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->subDays(3)->toDateString(),
        narration: 'Sold maize',
        recordedBy: $this->clerk->id,
    ));

    $this->service = app(ReversalService::class);

    $this->ask = function (?Transaction $target = null, ?User $who = null) {
        return $this->service->request(
            transaction: $target ?? $this->original,
            requestedBy: $who ?? $this->clerk,
            reason: 'The amount was typed wrong.',
        );
    };
});

it('records a request to reverse', function () {
    $request = ($this->ask)();

    expect($request)->toBeInstanceOf(ReversalRequest::class);
    expect($request->status)->toBe(ReversalRequest::PENDING);
    expect($request->reason)->toBe('The amount was typed wrong.');
    expect($request->requested_by)->toBe($this->clerk->id);
});

// asking is not the same as doing
it('writes nothing to the books when the request is made', function () {
    ($this->ask)();

    expect(Transaction::count())->toBe(1);
});

it('needs a reason', function () {
    expect(fn() => $this->service->request(
        transaction: $this->original,
        requestedBy: $this->clerk,
        reason: '',
    ))->toThrow(PostingFailed::class);
});

// a correction of a correction hides the trail
it('refuses to reverse an adjustment', function () {
    $adjustment = ($this->ask)();
    $posted = $this->service->approve($adjustment, $this->manager);

    expect(fn() => ($this->ask)($posted))->toThrow(PostingFailed::class);
});

it('refuses a second request for the same transaction', function () {
    ($this->ask)();

    expect(fn() => ($this->ask)())->toThrow(PostingFailed::class);
});

it('refuses to reverse something already reversed', function () {
    $request = ($this->ask)();
    $this->service->approve($request, $this->manager);

    expect(fn() => ($this->ask)())->toThrow(PostingFailed::class);
});

// the correction lands today, so a closed month is never reached into
it('still allows a cancellation when the original period has closed', function () {
    $later = AccountingPeriod::create([
        'name' => 'Next Period',
        'starts_on' => now()->addYear()->startOfYear()->toDateString(),
        'ends_on' => now()->addYear()->endOfYear()->toDateString(),
    ]);

    $this->period->close($this->manager);

    $this->travelTo(now()->addYear());

    expect(($this->ask)())->toBeInstanceOf(ReversalRequest::class);
});

// the person who asks cannot be the person who agrees
it('refuses approval by the person who asked', function () {
    $request = ($this->ask)();

    expect(fn() => $this->service->approve($request, $this->clerk))
        ->toThrow(PostingFailed::class);
});

it('posts the reversal when somebody else approves', function () {
    $request = ($this->ask)();

    $reversal = $this->service->approve($request, $this->manager);

    expect($reversal)->toBeInstanceOf(Transaction::class);
    expect($reversal->transaction_type)->toBe('ADJUSTMENT');
    expect($reversal->reverses_transaction_id)->toBe($this->original->id);
});

it('marks the request approved', function () {
    $request = ($this->ask)();

    $this->service->approve($request, $this->manager);

    expect($request->fresh()->status)->toBe(ReversalRequest::APPROVED);
    expect($request->fresh()->approved_by)->toBe($this->manager->id);
});

// the fix belongs to today, not to the day the mistake was made
it('dates the reversal today', function () {
    $request = ($this->ask)();

    $reversal = $this->service->approve($request, $this->manager);

    expect($reversal->transaction_date->toDateString())->toBe(now()->toDateString());
});

it('reverses the same amount', function () {
    $request = ($this->ask)();

    $reversal = $this->service->approve($request, $this->manager);

    expect($reversal->amount_minor)->toBe($this->original->amount_minor);
});

// each account keeps its place and the money moves to the other side
it('swaps the two sides', function () {
    $originalEntry = JournalEntry::where('transaction_id', $this->original->id)->first();

    $request = ($this->ask)();
    $reversal = $this->service->approve($request, $this->manager);

    $reversalEntry = JournalEntry::where('transaction_id', $reversal->id)->first();

    foreach ($originalEntry->lines as $index => $line) {
        $mirror = $reversalEntry->lines[$index];

        expect($mirror->ledger_account_id)->toBe($line->ledger_account_id);
        expect($mirror->debit_minor)->toBe($line->credit_minor);
        expect($mirror->credit_minor)->toBe($line->debit_minor);
    }
});

it('balances like any other entry', function () {
    $request = ($this->ask)();
    $reversal = $this->service->approve($request, $this->manager);

    expect(JournalEntry::where('transaction_id', $reversal->id)->first()->isBalanced())
        ->toBeTrue();
});

// after a reversal the farmer is back where they started
it('leaves the farmer with nothing on the books', function () {
    $request = ($this->ask)();
    $this->service->approve($request, $this->manager);

    $report = app(App\Services\Ledger\Reports\TrialBalanceService::class)->for(
        farmerProfileId: $this->profile->id,
        from: now()->startOfYear()->toDateString(),
        to: now()->endOfYear()->toDateString(),
    );

    expect($report->row($this->cash->id)->balanceMinor)->toBe(0);
});

it('says why in the reversal narration', function () {
    $request = ($this->ask)();

    $reversal = $this->service->approve($request, $this->manager);

    expect($reversal->narration)->toContain('The amount was typed wrong.');
});

// a refusal is part of the trail too
it('records a rejection without touching the books', function () {
    $request = ($this->ask)();

    $this->service->reject($request, $this->manager, 'The original was correct.');

    expect($request->fresh()->status)->toBe(ReversalRequest::REJECTED);
    expect(Transaction::count())->toBe(1);
});

it('refuses rejection by the person who asked', function () {
    $request = ($this->ask)();

    expect(fn() => $this->service->reject($request, $this->clerk, 'Never mind.'))
        ->toThrow(PostingFailed::class);
});

it('refuses to approve a request that is already settled', function () {
    $request = ($this->ask)();
    $this->service->approve($request, $this->manager);

    expect(fn() => $this->service->approve($request->fresh(), $this->manager))
        ->toThrow(PostingFailed::class);
});

it('refuses to approve a request that was rejected', function () {
    $request = ($this->ask)();
    $this->service->reject($request, $this->manager, 'No.');

    expect(fn() => $this->service->approve($request->fresh(), $this->manager))
        ->toThrow(PostingFailed::class);
});

// half a reversal is worse than none
it('writes nothing when the posting fails', function () {
    $request = ($this->ask)();

    $this->period->close($this->manager);

    try {
        $this->service->approve($request, $this->manager);
    } catch (PostingFailed) {
        // the throw is the point, the counts below are the test
    }

    expect(Transaction::count())->toBe(1);
    expect($request->fresh()->status)->toBe(ReversalRequest::PENDING);
});
