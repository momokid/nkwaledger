<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\ReversalRequest;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\ReversalService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

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
    $suspense = $account('Suspense A/C', $assetSub->id);

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

    TransactionTemplate::create([
        'name' => 'Correction',
        'slug' => 'correction',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $suspense->id,
        'settlement_side' => 'none',
    ]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    $this->cropCategory = FarmTypeCategory::create(['name' => 'Crop']);
    $this->livestockCategory = FarmTypeCategory::create(['name' => 'Livestock']);

    $posting = app(PostingService::class);

    $this->recordIncome = function (string $amount, string $date, ?FarmerProfile $who = null) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: ($who ?? $this->profile)->id,
            transactionTemplateId: $this->incomeTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->farmerUser->id,
        ));
    };

    $this->recordExpense = function (string $amount, string $date, ?FarmerProfile $who = null) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: ($who ?? $this->profile)->id,
            transactionTemplateId: $this->expenseTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->farmerUser->id,
        ));
    };
});

test('a guest is redirected to login', function () {
    $this->get('/farmer/dashboard')->assertRedirect('/login');
});

test('a farmer with no profile yet sees an empty dashboard, not an error', function () {
    $bare = User::factory()->create();
    $bare->assignRole('farmer');

    $this->actingAs($bare)->get('/farmer/dashboard')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('summary.total_income', 0)
            ->where('farm_produce.items', [])
            ->where('farm_produce.more_count', 0)
            ->where('breakdown.income_rows', [])
            ->where('recent_transactions', []));
});

test('shows income, expense and net profit for the last 30 days by default', function () {
    ($this->recordIncome)('500', now()->toDateString());
    ($this->recordExpense)('200', now()->toDateString());
    ($this->recordIncome)('1000', now()->subDays(40)->toDateString());

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Dashboard')
            ->where('summary.total_income', 50000)
            ->where('summary.total_expense', 20000)
            ->where('summary.net', 30000));
});

test('does not include another farmer\'s transactions', function () {
    $other = FarmerProfile::factory()->create();
    ($this->recordIncome)('9999.99', now()->toDateString(), $other);

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->where('summary.total_income', 0));
});

test('a custom date range can be requested', function () {
    ($this->recordIncome)('700', now()->subDays(90)->toDateString());

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(100)->toDateString() . '&to=' . now()->subDays(80)->toDateString())
        ->assertInertia(fn($page) => $page->where('summary.total_income', 70000));
});

test('income trend is up and good when income rose vs the previous period', function () {
    ($this->recordIncome)('250', now()->subDays(45)->toDateString());
    ($this->recordIncome)('500', now()->subDays(5)->toDateString());

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.income.direction', 'up')
            ->where('summary.trends.income.percent', 100)
            ->where('summary.trends.income.good', true));
});

test('expense trend is down and good when expenses fell vs the previous period', function () {
    ($this->recordExpense)('400', now()->subDays(45)->toDateString());
    ($this->recordExpense)('200', now()->subDays(5)->toDateString());

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.expense.direction', 'down')
            ->where('summary.trends.expense.percent', 50)
            ->where('summary.trends.expense.good', true));
});

test('expense trend is up and bad when expenses rose vs the previous period', function () {
    ($this->recordExpense)('100', now()->subDays(45)->toDateString());
    ($this->recordExpense)('300', now()->subDays(5)->toDateString());

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.expense.direction', 'up')
            ->where('summary.trends.expense.good', false));
});

test('percent is null when there is nothing to compare against in the previous period', function () {
    ($this->recordIncome)('500', now()->subDays(5)->toDateString());

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.income.direction', 'up')
            ->where('summary.trends.income.percent', null));
});

test('net trend reflects income minus expense movement, not just income', function () {
    ($this->recordIncome)('100', now()->subDays(45)->toDateString());
    ($this->recordExpense)('20', now()->subDays(45)->toDateString());
    ($this->recordIncome)('100', now()->subDays(5)->toDateString());
    ($this->recordExpense)('80', now()->subDays(5)->toDateString());

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.net.direction', 'down')
            ->where('summary.trends.net.good', false));
});

test('breakdown lists the ledger accounts behind income and expense', function () {
    ($this->recordIncome)('500', now()->toDateString());
    ($this->recordExpense)('200', now()->toDateString());

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page
            ->where('breakdown.income_rows.0.account', 'Sales A/C')
            ->where('breakdown.income_rows.0.amount', 50000)
            ->where('breakdown.expense_rows.0.account', 'Feed A/C')
            ->where('breakdown.expense_rows.0.amount', 20000));
});

test('breakdown only includes rows from the selected date range', function () {
    ($this->recordIncome)('500', now()->toDateString());
    ($this->recordIncome)('1000', now()->subDays(40)->toDateString());

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->where('breakdown.income_rows.0.amount', 50000));
});

test('recent transactions shows the latest 5, newest first', function () {
    ($this->recordIncome)('100', now()->subDays(4)->toDateString());
    ($this->recordIncome)('200', now()->subDays(3)->toDateString());
    ($this->recordExpense)('50', now()->subDays(2)->toDateString());
    ($this->recordIncome)('300', now()->subDays(1)->toDateString());
    ($this->recordExpense)('60', now()->toDateString());
    ($this->recordIncome)('400', now()->subDays(5)->toDateString());

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page
            ->has('recent_transactions', 5)
            ->where('recent_transactions.0.amount', 6000)
            ->where('recent_transactions.0.income', false)
            ->where('recent_transactions.1.amount', 30000)
            ->where('recent_transactions.1.income', true));
});

test('recent transactions shows the template name and date', function () {
    ($this->recordIncome)('250', now()->toDateString());

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page
            ->where('recent_transactions.0.name', 'Crop Sale')
            ->where('recent_transactions.0.date', now()->toDateString()));
});

test('a cancelled transaction does not appear in recent transactions', function () {
    $transaction = ($this->recordIncome)('250', now()->toDateString());

    app(ReversalService::class)->request($transaction, $this->farmerUser, 'Wrong amount');
    $requestModel = ReversalRequest::where('transaction_id', $transaction->id)->first();
    $approver = User::factory()->create();
    app(ReversalService::class)->approve($requestModel, $approver);

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->where('recent_transactions', []));
});

test('farm produce lists every farm type the farmer has approved, crop or livestock, with its unit', function () {
    $cropType = FarmType::create(['name' => 'Maize', 'category_id' => $this->cropCategory->id]);
    $livestockType = FarmType::create(['name' => 'Goats', 'category_id' => $this->livestockCategory->id]);

    $cropUnit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $cropType->id,
    ]);
    FarmUnitStock::factory()->confirmed()->create([
        'farm_unit_id' => $cropUnit->id,
        'opening_quantity' => 2.5,
        'unit_of_measure' => 'acres',
    ]);

    $livestockUnit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $livestockType->id,
    ]);
    FarmUnitStock::factory()->confirmed()->create([
        'farm_unit_id' => $livestockUnit->id,
        'opening_quantity' => 50,
        'unit_of_measure' => 'goats',
    ]);

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page
            ->where('farm_produce.items.0.type', 'Goats')
            ->where('farm_produce.items.0.quantity', '50.00')
            ->where('farm_produce.items.0.unit', 'goats')
            ->where('farm_produce.items.1.type', 'Maize')
            ->where('farm_produce.items.1.quantity', '2.50')
            ->where('farm_produce.items.1.unit', 'acres')
            ->where('farm_produce.more_count', 0));
});

test('farm produce shows at most 4 types alphabetically and counts the rest', function () {
    foreach (['Chickens', 'Ducks', 'Goats', 'Pigs', 'Rabbits'] as $name) {
        $type = FarmType::create(['name' => $name, 'category_id' => $this->livestockCategory->id]);
        $unit = FarmUnit::factory()->approved()->create([
            'farmer_profile_id' => $this->profile->id,
            'farm_type_id' => $type->id,
        ]);
        FarmUnitStock::factory()->confirmed()->create([
            'farm_unit_id' => $unit->id,
            'opening_quantity' => 10,
        ]);
    }

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page
            ->has('farm_produce.items', 4)
            ->where('farm_produce.items.0.type', 'Chickens')
            ->where('farm_produce.items.3.type', 'Pigs')
            ->where('farm_produce.more_count', 1));
});

test('unconfirmed or rejected stock does not count toward farm produce quantity', function () {
    $type = FarmType::create(['name' => 'Layers', 'category_id' => $this->livestockCategory->id]);
    $unit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
    ]);
    FarmUnitStock::factory()->confirmed()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 50]);
    FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 999]);

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->where('farm_produce.items.0.quantity', '50.00'));
});

test('an approved unit with no confirmed stock yet still appears at zero', function () {
    $type = FarmType::create(['name' => 'Rabbits', 'category_id' => $this->livestockCategory->id]);
    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $type->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page
            ->where('farm_produce.items.0.type', 'Rabbits')
            ->where('farm_produce.items.0.quantity', '0.00'));
});
