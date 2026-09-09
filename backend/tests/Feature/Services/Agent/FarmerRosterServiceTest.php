<?php

use App\Models\AccountingPeriod;
use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Agent\FarmerRosterService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;

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

    $this->agentUser = User::factory()->create();
    $this->agentUser->assignRole('agent');

    $posting = app(PostingService::class);

    $this->recordIncome = function (FarmerProfile $farmer, string $amount, string $date) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $this->incomeTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->agentUser->id,
        ));
    };

    $this->service = app(FarmerRosterService::class);
    $this->from = now()->subDays(29)->toDateString();
    $this->to = now()->toDateString();
});

test('totals are summed across every assigned farmer', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);

    ($this->recordIncome)($farmerA, '500', now()->toDateString());
    ($this->recordIncome)($farmerB, '300', now()->toDateString());

    [$income,, $activeCount] = $this->service->totalsFor($this->agentUser->id, $this->from, $this->to);

    expect($income)->toBe(80000);
    expect($activeCount)->toBe(2);
});

test('rows are empty unless explicitly requested', function () {
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);

    [,,, $rows] = $this->service->totalsFor($this->agentUser->id, $this->from, $this->to);

    expect($rows)->toBe([]);
});

test('rows include name, community, and status when requested', function () {
    $community = Community::factory()->create(['name' => 'Ejisu']);
    $farmer = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentUser->id,
        'community_id' => $community->id,
    ]);
    $farmer->user->update(['surname' => 'Mensah', 'first_name' => 'Ama']);

    ($this->recordIncome)($farmer, '500', now()->toDateString());

    [,,, $rows] = $this->service->totalsFor($this->agentUser->id, $this->from, $this->to, withRows: true);

    expect($rows[0]['name'])->toBe('Mensah Ama');
    expect($rows[0]['community'])->toBe('Ejisu');
    expect($rows[0]['status'])->toBe('active');
    expect($rows[0]['income'])->toBe(50000);
});

test('a farmer with no transactions is dormant with no last activity', function () {
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);

    [,,, $rows] = $this->service->totalsFor($this->agentUser->id, $this->from, $this->to, withRows: true);

    expect($rows[0]['status'])->toBe('dormant');
    expect($rows[0]['last_activity'])->toBeNull();
});

test('last activity reflects the most recent transaction even outside the period', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    ($this->recordIncome)($farmer, '500', now()->subDays(60)->toDateString());

    [,,, $rows] = $this->service->totalsFor($this->agentUser->id, $this->from, $this->to, withRows: true);

    expect($rows[0]['last_activity'])->toBe(now()->subDays(60)->toDateString());
    expect($rows[0]['status'])->toBe('dormant');
});

test('another agent\'s farmers are excluded', function () {
    $otherAgent = User::factory()->create();
    FarmerProfile::factory()->create(['assigned_agent_id' => $otherAgent->id]);

    [$income,, $activeCount, $rows] = $this->service->totalsFor($this->agentUser->id, $this->from, $this->to, withRows: true);

    expect($income)->toBe(0);
    expect($activeCount)->toBe(0);
    expect($rows)->toBe([]);
});
