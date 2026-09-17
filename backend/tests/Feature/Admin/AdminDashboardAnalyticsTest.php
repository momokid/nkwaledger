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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->agentA = User::factory()->create();
    $this->agentA->assignRole('agent');

    $this->agentB = User::factory()->create();
    $this->agentB->assignRole('agent');

    $posting = app(PostingService::class);

    $this->recordIncome = function (FarmerProfile $farmer, string $amount, string $date) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $this->incomeTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->agentA->id,
        ));
    };

    $this->recordExpense = function (FarmerProfile $farmer, string $amount, string $date) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $this->expenseTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->agentA->id,
        ));
    };

    $this->periodFrom = now()->subDays(29)->toDateString();
    $this->periodTo = now()->toDateString();
});

test('the page renders the platform snapshot for the given date range', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentB->id]);

    ($this->recordIncome)($farmerA, '500', now()->toDateString());
    ($this->recordIncome)($farmerB, '300', now()->toDateString());
    ($this->recordExpense)($farmerA, '200', now()->toDateString());

    $this->actingAs($this->admin)
        ->get("/admin/dashboard?from={$this->periodFrom}&to={$this->periodTo}")
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Admin/Dashboard')
            ->where('snapshot.total_income', 80000)
            ->where('snapshot.total_expense', 20000)
            ->where('snapshot.net', 60000)
            ->where('snapshot.total_farmers', 2)
            ->where('snapshot.total_agents', 2)
            ->where('snapshot.active_farmers', 2)
            ->where('snapshot.active_agents', 2)
            ->where('filters.from', $this->periodFrom)
            ->where('filters.to', $this->periodTo));
});

// active_farmers/active_agents are always the trailing 30 days, whatever the filter says
test('the active counts stay on the fixed 30-day window regardless of the requested filter range', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);
    ($this->recordIncome)($farmer, '500', now()->toDateString());

    $this->actingAs($this->admin)
        ->get('/admin/dashboard?from=' . now()->subMonths(6)->toDateString() . '&to=' . now()->subMonths(5)->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('snapshot.active_farmers', 1)
            ->where('snapshot.active_agents', 1));
});

test('defaults apply when no date range is given', function () {
    $this->actingAs($this->admin)->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('filters.from', now()->subDays(29)->toDateString())
            ->where('filters.to', now()->toDateString())
            ->where('sort', 'net'));
});

test('the leaderboard includes every agent with their own period totals', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentB->id]);

    ($this->recordIncome)($farmerA, '500', now()->toDateString());
    ($this->recordIncome)($farmerB, '900', now()->toDateString());

    $this->actingAs($this->admin)
        ->get("/admin/dashboard?from={$this->periodFrom}&to={$this->periodTo}")
        ->assertInertia(fn($page) => $page
            ->has('leaderboard', 2)
            ->where('leaderboard.0.agent_id', $this->agentB->id)
            ->where('leaderboard.0.net', 90000)
            ->where('leaderboard.1.agent_id', $this->agentA->id));
});

test('sorting by records_logged changes the leaderboard order', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentB->id]);

    // agent A has the higher net, but agent B logged more individual records
    ($this->recordIncome)($farmerA, '900', now()->toDateString());
    ($this->recordIncome)($farmerB, '10', now()->toDateString());
    ($this->recordIncome)($farmerB, '10', now()->subDay()->toDateString());

    $this->actingAs($this->admin)
        ->get("/admin/dashboard?from={$this->periodFrom}&to={$this->periodTo}&sort=activity")
        ->assertInertia(fn($page) => $page
            ->where('sort', 'activity')
            ->where('leaderboard.0.agent_id', $this->agentB->id)
            ->where('leaderboard.0.records_logged', 2));
});

test('an unrecognised sort value falls back to net', function () {
    $this->actingAs($this->admin)
        ->get('/admin/dashboard?sort=nonsense')
        ->assertInertia(fn($page) => $page->where('sort', 'net'));
});
