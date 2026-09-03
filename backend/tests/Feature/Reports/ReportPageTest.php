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
        'name' => 'I sold my farm produce',
        'slug' => 'produce_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
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
        'name' => 'This Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

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

    $this->sell = function (string $amount) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $this->profile->id,
            transactionTemplateId: $this->saleTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: now()->toDateString(),
            recordedBy: $this->farmerUser->id,
        ));
    };

    $this->spend = function (string $amount, ?FarmUnit $unit = null) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $this->profile->id,
            transactionTemplateId: $this->feedTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: now()->toDateString(),
            farmUnitId: ($unit ?? $this->approvedUnit)->id,
            recordedBy: $this->farmerUser->id,
        ));
    };
});

it('shows the reports page to a farmer', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Reports/Index'));
});

it('turns a stranger away', function () {
    $this->actingAs(User::factory()->create())
        ->get('/my-reports')
        ->assertForbidden();
});

// a trial balance means nothing to a farmer
it('offers a farmer two reports', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports')
        ->assertInertia(fn($page) => $page->where('available', ['statement', 'income']));
});

it('offers staff all three', function () {
    $this->actingAs($this->admin)
        ->get("/admin/farmers/{$this->profile->uuid}/reports")
        ->assertInertia(fn($page) => $page->where('available', ['statement', 'income', 'trial-balance']));
});

it('shows the statement by default', function () {
    ($this->sell)('250');

    $this->actingAs($this->farmerUser)
        ->get('/my-reports')
        ->assertInertia(fn($page) => $page
            ->where('kind', 'statement')
            ->has('report.rows', 1));
});

it('shows income and expenditure when asked', function () {
    ($this->sell)('250');
    ($this->spend)('100');

    $this->actingAs($this->farmerUser)
        ->get('/my-reports?kind=income')
        ->assertInertia(fn($page) => $page
            ->where('kind', 'income')
            ->where('report.total_income', 25000)
            ->where('report.total_expense', 10000)
            ->where('report.net', 15000));
});

it('shows a trial balance when staff ask', function () {
    ($this->sell)('250');

    $this->actingAs($this->admin)
        ->get("/admin/farmers/{$this->profile->uuid}/reports?kind=trial-balance")
        ->assertInertia(fn($page) => $page
            ->where('kind', 'trial-balance')
            ->where('report.is_balanced', true));
});

// the one report a farmer should never be handed
it('refuses a farmer a trial balance', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports?kind=trial-balance')
        ->assertInertia(fn($page) => $page->where('kind', 'statement'));
});

// every printed report says what it is and who it is for
it('carries the header on every report', function () {
    ($this->sell)('250');

    foreach (['statement', 'income'] as $kind) {
        $this->actingAs($this->farmerUser)
            ->get("/my-reports?kind={$kind}")
            ->assertInertia(fn($page) => $page
                ->has('report.header.title')
                ->has('report.header.verification_code')
                ->where('report.header.farmer_reference', $this->profile->uuid));
    }
});

it('reads the dates it was asked for', function () {
    ($this->sell)('250');

    $this->actingAs($this->farmerUser)
        ->get('/my-reports?from=' . now()->addYear()->startOfYear()->toDateString()
            . '&to=' . now()->addYear()->endOfYear()->toDateString())
        ->assertInertia(fn($page) => $page->has('report.rows', 0));
});

it('defaults to this year', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports')
        ->assertInertia(fn($page) => $page
            ->where('filters.from', now()->startOfYear()->toDateString())
            ->where('filters.to', now()->endOfYear()->toDateString()));
});

// a farmer sees everything of their own, a bank does not
it('includes provisional records for a farmer', function () {
    ($this->spend)('100', $this->pendingUnit);

    $this->actingAs($this->farmerUser)
        ->get('/my-reports?kind=income')
        ->assertInertia(fn($page) => $page->where('report.total_expense', 10000));
});

it('leaves provisional records out for staff by default', function () {
    ($this->spend)('100', $this->pendingUnit);

    $this->actingAs($this->admin)
        ->get("/admin/farmers/{$this->profile->uuid}/reports?kind=income")
        ->assertInertia(fn($page) => $page->where('report.total_expense', 0));
});

it('lets staff put provisional records back in', function () {
    ($this->spend)('100', $this->pendingUnit);

    $this->actingAs($this->admin)
        ->get("/admin/farmers/{$this->profile->uuid}/reports?kind=income&provisional=1")
        ->assertInertia(fn($page) => $page->where('report.total_expense', 10000));
});

it('says what was held back', function () {
    ($this->spend)('100', $this->pendingUnit);

    $this->actingAs($this->admin)
        ->get("/admin/farmers/{$this->profile->uuid}/reports?kind=income")
        ->assertInertia(fn($page) => $page->where('report.provisional_held_back', 10000));
});

// an agent works their own book and nobody else's
it('shows the page to an agent for a farmer they hold', function () {
    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$this->profile->uuid}/reports")
        ->assertOk();
});

it('hides a farmer the agent does not hold', function () {
    $other = FarmerProfile::factory()->create();

    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$other->uuid}/reports")
        ->assertNotFound();
});

it('names the farmer on the page', function () {
    $this->actingAs($this->admin)
        ->get("/admin/farmers/{$this->profile->uuid}/reports")
        ->assertInertia(fn($page) => $page->where(
            'farmer.name',
            "{$this->profile->user?->surname} {$this->profile->user?->first_name}",
        ));
});

it('comes back empty for a farmer with no records', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports?kind=income')
        ->assertInertia(fn($page) => $page
            ->has('report.income_rows', 0)
            ->where('report.net', 0));
});
