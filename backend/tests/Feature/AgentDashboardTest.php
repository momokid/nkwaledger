<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
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
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use App\Models\Community;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

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

    $this->recordExpense = function (FarmerProfile $farmer, string $amount, string $date) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $this->expenseTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->agentUser->id,
        ));
    };
});

test('a guest is redirected to login', function () {
    $this->get('/agent/dashboard')->assertRedirect('/login');
});

test('an agent with no assigned farmers sees an empty dashboard, not an error', function () {
    $this->actingAs($this->agentUser)->get('/agent/dashboard')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Agent/Dashboard')
            ->where('summary.total_income', 0)
            ->where('summary.total_expense', 0)
            ->where('summary.net', 0)
            ->where('farmer_count', 0));
});

test('income and expense are summed across every assigned farmer', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);

    ($this->recordIncome)($farmerA, '500', now()->toDateString());
    ($this->recordIncome)($farmerB, '300', now()->toDateString());
    ($this->recordExpense)($farmerA, '200', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/dashboard')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('summary.total_income', 80000)
            ->where('summary.total_expense', 20000)
            ->where('summary.net', 60000));
});

test('a farmer with no activity in the period is not counted', function () {
    $active = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]); // no transactions

    ($this->recordIncome)($active, '500', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/dashboard')
        ->assertInertia(fn($page) => $page->where('farmer_count', 1));
});

test('another agent\'s farmers are not included', function () {
    $otherAgent = User::factory()->create();
    $otherAgent->assignRole('agent');
    $theirFarmer = FarmerProfile::factory()->create(['assigned_agent_id' => $otherAgent->id]);

    ($this->recordIncome)($theirFarmer, '999', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/dashboard')
        ->assertInertia(fn($page) => $page->where('summary.total_income', 0));
});

test('trend is aggregated across all assigned farmers', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);

    ($this->recordIncome)($farmerA, '100', now()->subDays(45)->toDateString());
    ($this->recordIncome)($farmerB, '150', now()->subDays(45)->toDateString());

    ($this->recordIncome)($farmerA, '300', now()->subDays(5)->toDateString());
    ($this->recordIncome)($farmerB, '200', now()->subDays(5)->toDateString());

    $this->actingAs($this->agentUser)
        ->get('/agent/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.income.direction', 'up')
            ->where('summary.trends.income.good', true));
});

test('roster includes each assigned farmer with community and period totals', function () {
    $community = Community::factory()->create(['name' => 'Ejisu']);
    $farmer = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentUser->id,
        'community_id' => $community->id,
    ]);
    $farmer->user->update(['surname' => 'Mensah', 'first_name' => 'Ama']);

    ($this->recordIncome)($farmer, '500', now()->toDateString());
    ($this->recordExpense)($farmer, '200', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/dashboard')
        ->assertInertia(fn($page) => $page
            ->where('roster.0.name', 'Mensah Ama')
            ->where('roster.0.community', 'Ejisu')
            ->where('roster.0.income', 50000)
            ->where('roster.0.expense', 20000)
            ->where('roster.0.status', 'active'));
});

test('a farmer with no transactions is dormant with zero totals and no last activity', function () {
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);

    $this->actingAs($this->agentUser)->get('/agent/dashboard')
        ->assertInertia(fn($page) => $page
            ->where('roster.0.status', 'dormant')
            ->where('roster.0.income', 0)
            ->where('roster.0.expense', 0)
            ->where('roster.0.last_activity', null));
});

test('last activity reflects the most recent transaction even outside the current period', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    ($this->recordIncome)($farmer, '500', now()->subDays(60)->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/dashboard')
        ->assertInertia(fn($page) => $page
            ->where('roster.0.last_activity', now()->subDays(60)->toDateString())
            ->where('roster.0.status', 'dormant'));
});

test('another agent\'s farmers do not appear in the roster', function () {
    $otherAgent = User::factory()->create();
    $otherAgent->assignRole('agent');
    FarmerProfile::factory()->create(['assigned_agent_id' => $otherAgent->id]);

    $this->actingAs($this->agentUser)->get('/agent/dashboard')
        ->assertInertia(fn($page) => $page->where('roster', []));
});
