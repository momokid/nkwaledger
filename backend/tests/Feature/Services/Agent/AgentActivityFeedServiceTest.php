<?php

use App\Enums\MovementReason;
use App\Models\AccountingPeriod;
use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
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
use App\Services\Agent\AgentActivityFeedService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['app.report_secret' => 'testing-secret']);

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

    $account = fn(string $name, int $subcategoryId, bool $isSettlement = false) => LedgerAccount::create([
        'name' => $name,
        'control_id' => $control->id,
        'subcategory_id' => $subcategoryId,
        'type_id' => $type->id,
        'is_settlement' => $isSettlement,
    ]);

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $this->sales = $account('Sales A/C', $incomeSub->id);
    $this->feed = $account('Feed A/C', $expenseSub->id);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->incomeTemplate = TransactionTemplate::create([
        'name' => 'Crop Sale',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    $this->expenseTemplate = TransactionTemplate::create([
        'name' => 'Feed Purchase',
        'slug' => 'feed_purchase',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $this->feed->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
    ]);

    $this->agentUser = User::factory()->create();

    $posting = app(PostingService::class);

    $this->recordIncome = function (FarmerProfile $farmer, string $amount) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $this->incomeTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: now()->toDateString(),
            recordedBy: $this->agentUser->id,
        ));
    };

    $this->recordExpense = function (FarmerProfile $farmer, string $amount) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $this->expenseTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: now()->toDateString(),
            recordedBy: $this->agentUser->id,
        ));
    };

    $this->community = Community::factory()->create();
    $this->farmType = FarmType::factory()->withCategory()->create();

    $this->addMovement = function (FarmerProfile $farmer, MovementReason $reason, string $quantity, bool $increase = true) {
        $unit = FarmUnit::factory()->approved()->create([
            'farmer_profile_id' => $farmer->id,
            'farm_type_id' => $this->farmType->id,
            'community_id' => $this->community->id,
        ]);
        $stock = FarmUnitStock::factory()->create([
            'farm_unit_id' => $unit->id,
            'unit_of_measure' => 'birds',
        ]);

        return $stock->movements()->create([
            'reason' => $reason,
            'quantity' => $quantity,
            'is_increase' => $increase,
            'occurred_on' => now(),
            'recorded_by' => $this->agentUser->id,
        ]);
    };

    $this->service = app(AgentActivityFeedService::class);
    $this->from = now()->subDays(29)->toDateString();
    $this->to = now()->toDateString();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('only active farmers appear in the feed', function () {
    $active = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $active->user->update(['surname' => 'Active', 'first_name' => 'Farmer']);
    ($this->recordIncome)($active, '500');

    $dormant = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $dormant->user->update(['surname' => 'Dormant', 'first_name' => 'Farmer']);
    ($this->addMovement)($dormant, MovementReason::Birth, '2');

    $feed = $this->service->recentFor($this->agentUser->id, $this->from, $this->to);

    expect(collect($feed)->pluck('farmer')->unique()->all())->toBe(['Active Farmer']);
});

test('includes a recent transaction with its details', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $farmer->user->update(['surname' => 'Mensah', 'first_name' => 'Ama']);
    ($this->recordIncome)($farmer, '500');

    $feed = $this->service->recentFor($this->agentUser->id, $this->from, $this->to);
    $entry = collect($feed)->firstWhere('kind', 'transaction');

    expect($entry['farmer'])->toBe('Mensah Ama');
    expect($entry['action'])->toBe('Logged income');
    expect($entry['detail'])->toBe('Crop Sale');
    expect($entry['amount_minor'])->toBe(50000);
    expect($entry['is_income'])->toBeTrue();
});

test('includes a recent stock movement', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $farmer->user->update(['surname' => 'Owusu', 'first_name' => 'Kwabena']);
    ($this->recordIncome)($farmer, '500');
    ($this->addMovement)($farmer, MovementReason::Birth, '3');

    $feed = $this->service->recentFor($this->agentUser->id, $this->from, $this->to);
    $entry = collect($feed)->firstWhere('reason', 'New birth');

    expect($entry)->not->toBeNull();
    expect($entry['farmer'])->toBe('Owusu Kwabena');
    expect($entry['quantity'])->toBe('3.00');
    expect($entry['is_increase'])->toBeTrue();
});

test('excludes a rejected movement', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    ($this->recordIncome)($farmer, '500');
    $movement = ($this->addMovement)($farmer, MovementReason::Death, '2', false);
    $movement->reject($this->agentUser->id, 'Miscounted');

    $feed = $this->service->recentFor($this->agentUser->id, $this->from, $this->to);

    expect(collect($feed)->where('reason', 'Death'))->toHaveCount(0);
});

test('merges both sources and sorts most recent first', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $base = Carbon::now();

    Carbon::setTestNow($base->copy()->subMinutes(10));
    ($this->recordExpense)($farmer, '100');

    Carbon::setTestNow($base->copy()->subMinutes(5));
    ($this->addMovement)($farmer, MovementReason::Birth, '2');

    Carbon::setTestNow($base);
    ($this->recordIncome)($farmer, '500');

    Carbon::setTestNow();

    $feed = $this->service->recentFor($this->agentUser->id, $this->from, $this->to);

    expect($feed[0]['action'])->toBe('Logged income');
    expect(collect($feed)->pluck('action')->filter()->last())->toBe('Logged expense');
    expect(collect($feed)->pluck('reason')->filter()->all())->toContain('New birth');
});

test('respects the limit', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    ($this->recordIncome)($farmer, '500');

    foreach (range(1, 6) as $i) {
        ($this->addMovement)($farmer, MovementReason::Birth, '1');
    }

    $feed = $this->service->recentFor($this->agentUser->id, $this->from, $this->to, limit: 5);

    expect($feed)->toHaveCount(5);
});

test('another agent\'s farmers are excluded', function () {
    $otherAgent = User::factory()->create();
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $otherAgent->id]);
    ($this->recordIncome)($farmer, '500');

    $feed = $this->service->recentFor($this->agentUser->id, $this->from, $this->to);

    expect($feed)->toBe([]);
});
