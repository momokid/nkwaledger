<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
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

    $category = FarmTypeCategory::create(['name' => 'Livestock']);

    $farmType = FarmType::create([
        'name' => 'Goats',
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $this->saleTemplate = TransactionTemplate::create([
        'name' => 'I sold my produce',
        'slug' => 'produce_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
        'farm_type_category_id' => $category->id,
    ]);

    $this->feedTemplate = TransactionTemplate::create([
        'name' => 'I bought feed',
        'slug' => 'feed_purchase',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $this->feed->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
        'requires_farm_unit' => true,
        'farm_type_category_id' => $category->id,
    ]);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');

    $this->profile = FarmerProfile::factory()->create([
        'user_id' => $this->farmerUser->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $this->profile->farmTypes()->attach($farmType->id);

    $this->approvedUnit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'approved_at' => now()->subMonths(6),
    ]);

    $this->pendingUnit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'approved_at' => null,
    ]);

    $posting = app(PostingService::class);

    $this->sell = function (string $amount, ?string $date = null, ?FarmerProfile $who = null) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: ($who ?? $this->profile)->id,
            transactionTemplateId: $this->saleTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date ?? now()->toDateString(),
            narration: 'Sold maize',
            recordedBy: $this->farmerUser->id,
        ));
    };

    $this->spend = function (string $amount, ?FarmUnit $unit = null, ?string $date = null) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $this->profile->id,
            transactionTemplateId: $this->feedTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date ?? now()->toDateString(),
            farmUnitId: ($unit ?? $this->approvedUnit)->id,
            recordedBy: $this->farmerUser->id,
        ));
    };
});

it('shows the list to a farmer', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Transactions/Index'));
});

it('turns a stranger away from the list', function () {
    $this->actingAs(User::factory()->create())
        ->get('/my-records')
        ->assertForbidden();
});

it('lists what the farmer recorded', function () {
    ($this->sell)('250');
    ($this->spend)('100');

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->has('statement.rows', 2));
});

// the three numbers a farmer looks for first
it('shows money in, money out and what is left', function () {
    ($this->sell)('250');
    ($this->spend)('100');

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page
            ->where('statement.total_in', 25000)
            ->where('statement.total_out', 10000)
            ->where('statement.closing_balance', 15000));
});

it('carries the reference a farmer can read out', function () {
    $transaction = ($this->sell)('250');

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->where('statement.rows.0.reference', $transaction->reference));
});

// the farmer sees everything of their own, marked
it('shows records waiting on approval', function () {
    ($this->spend)('100', $this->approvedUnit);
    ($this->spend)('50', $this->pendingUnit);

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->has('statement.rows', 2));
});

it('marks which rows are still waiting', function () {
    ($this->spend)('50', $this->pendingUnit);

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->where('statement.rows.0.is_provisional', true));
});

it('says how much is waiting on approval', function () {
    ($this->spend)('100', $this->approvedUnit);
    ($this->spend)('50', $this->pendingUnit);

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->where('statement.provisional_held_back', 5000));
});

// this month unless they ask for something else
it('defaults to this month', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page
            ->where('filters.from', now()->startOfMonth()->toDateString())
            ->where('filters.to', now()->endOfMonth()->toDateString()));
});

it('reads the dates it was asked for', function () {
    ($this->sell)('250', now()->subMonths(3)->toDateString());
    ($this->sell)('100', now()->toDateString());

    $this->actingAs($this->farmerUser)
        ->get('/my-records?from=' . now()->startOfMonth()->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page->has('statement.rows', 1));
});

// one farmer's book never shows another farmer's money
it('reads only the farmer signed in', function () {
    ($this->sell)('250');
    ($this->sell)('900', null, FarmerProfile::factory()->create());

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->has('statement.rows', 1));
});

it('breaks a long list into pages', function () {
    foreach (range(1, 5) as $day) {
        ($this->sell)('100', now()->startOfMonth()->addDays($day)->toDateString());
    }

    $this->actingAs($this->farmerUser)
        ->get('/my-records?per_page=2')
        ->assertInertia(fn($page) => $page
            ->has('statement.rows', 2)
            ->where('statement.last_page', 3));
});

// a running balance that restarts on page two is a lie
it('carries the balance onto the next page', function () {
    foreach (range(1, 5) as $day) {
        ($this->sell)('100', now()->startOfMonth()->addDays($day)->toDateString());
    }

    $this->actingAs($this->farmerUser)
        ->get('/my-records?per_page=2&page=2')
        ->assertInertia(fn($page) => $page->where('statement.opening_balance', 20000));
});

it('comes back empty when nothing was recorded', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page
            ->has('statement.rows', 0)
            ->where('statement.closing_balance', 0));
});

// the agent's version, with the farmer named in the address
it('shows the list to an agent', function () {
    ($this->sell)('250');

    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$this->profile->uuid}/records")
        ->assertOk()
        ->assertInertia(fn($page) => $page->has('statement.rows', 1));
});

it('hides a farmer the agent does not hold', function () {
    $other = FarmerProfile::factory()->create();

    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$other->uuid}/records")
        ->assertNotFound();
});
