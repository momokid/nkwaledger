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
    $this->get('/agent/reports/ranking')->assertRedirect('/login');
});

test('an agent sees the ranking screen', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/ranking')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Agent/Reports/Ranking'));
});

test('a user without the view permission is refused', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/agent/reports/ranking')->assertForbidden();
});

test('ranks farmers by income, highest first, by default', function () {
    $low = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $low->user->update(['surname' => 'Low', 'first_name' => 'Earner']);
    ($this->recordIncome)($low, '200', now()->toDateString());

    $high = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $high->user->update(['surname' => 'High', 'first_name' => 'Earner']);
    ($this->recordIncome)($high, '900', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/reports/ranking')
        ->assertInertia(fn($page) => $page
            ->where('roster.0.name', 'High Earner')
            ->where('roster.0.rank', 1)
            ->where('roster.1.name', 'Low Earner')
            ->where('roster.1.rank', 2));
});

test('ranks by net when asked', function () {
    $bigSpender = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $bigSpender->user->update(['surname' => 'Big', 'first_name' => 'Spender']);
    ($this->recordIncome)($bigSpender, '900', now()->toDateString());
    ($this->recordExpense)($bigSpender, '800', now()->toDateString());

    $frugal = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $frugal->user->update(['surname' => 'Frugal', 'first_name' => 'Farmer']);
    ($this->recordIncome)($frugal, '300', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/reports/ranking?sort=net')
        ->assertInertia(fn($page) => $page
            ->where('roster.0.name', 'Frugal Farmer')
            ->where('roster.1.name', 'Big Spender'));
});

test('an invalid sort value falls back to income', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    ($this->recordIncome)($farmer, '500', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/reports/ranking?sort=nonsense')
        ->assertInertia(fn($page) => $page->where('sort', 'income'));
});

test('the print view renders for an agent', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/ranking/print')
        ->assertOk()
        ->assertSee('Farmer Ranking');
});

test('the print view carries the internal-use notice', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/ranking/print')
        ->assertSee('Internal use only');
});

test('a guest cannot reach the print view', function () {
    $this->get('/agent/reports/ranking/print')->assertRedirect('/login');
});
