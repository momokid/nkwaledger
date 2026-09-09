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
    $this->eggSales = $account('Egg Sales A/C', $incomeSub->id);
    $this->feed = $account('Feed A/C', $expenseSub->id);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->cropTemplate = TransactionTemplate::create([
        'name' => 'Crop Sale',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    $this->eggTemplate = TransactionTemplate::create([
        'name' => 'Egg Sale',
        'slug' => 'egg_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->eggSales->id,
        'settlement_side' => 'debit',
    ]);

    $this->feedTemplate = TransactionTemplate::create([
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

    $this->record = function (FarmerProfile $farmer, TransactionTemplate $template, string $amount, string $date) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $template->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->agentUser->id,
        ));
    };
});

test('a guest is redirected to login', function () {
    $this->get('/agent/reports/income-summary')->assertRedirect('/login');
});

test('an agent sees the income summary screen', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/income-summary')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Agent/Reports/IncomeSummary'));
});

test('a user without the view permission is refused', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/agent/reports/income-summary')->assertForbidden();
});

test('totals are summed across every assigned farmer', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);

    ($this->record)($farmerA, $this->cropTemplate, '500', now()->toDateString());
    ($this->record)($farmerB, $this->cropTemplate, '300', now()->toDateString());
    ($this->record)($farmerA, $this->feedTemplate, '100', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/reports/income-summary')
        ->assertInertia(fn($page) => $page
            ->where('summary.total_income', 80000)
            ->where('summary.total_expense', 10000)
            ->where('summary.net', 70000));
});

test('income is broken down by account, merged across farmers', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);

    ($this->record)($farmerA, $this->cropTemplate, '500', now()->toDateString());
    ($this->record)($farmerB, $this->cropTemplate, '300', now()->toDateString());
    ($this->record)($farmerA, $this->eggTemplate, '100', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/reports/income-summary')
        ->assertInertia(fn($page) => $page
            ->where('summary.income_by_account.0.account', 'Sales A/C')
            ->where('summary.income_by_account.0.amount', 80000)
            ->where('summary.income_by_account.1.account', 'Egg Sales A/C')
            ->where('summary.income_by_account.1.amount', 10000));
});

test('another agent\'s farmers are excluded', function () {
    $otherAgent = User::factory()->create();
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $otherAgent->id]);
    ($this->record)($farmer, $this->cropTemplate, '500', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/reports/income-summary')
        ->assertInertia(fn($page) => $page->where('summary.total_income', 0));
});

test('the print view renders for an agent', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/income-summary/print')
        ->assertOk()
        ->assertSee('Income & Expense Summary');
});

test('the print view carries the internal-use notice', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/income-summary/print')
        ->assertSee('Internal use only');
});

test('a guest cannot reach the print view', function () {
    $this->get('/agent/reports/income-summary/print')->assertRedirect('/login');
});
