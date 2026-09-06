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
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
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

    $account = fn(string $name, int $subcategoryId) => LedgerAccount::create([
        'name' => $name,
        'control_id' => $control->id,
        'subcategory_id' => $subcategoryId,
        'type_id' => $type->id,
    ]);

    $this->cash = $account('Cash A/C', $assetSub->id);
    $this->sales = $account('Sales A/C', $incomeSub->id);
    $this->feed = $account('Feed A/C', $expenseSub->id);

    $this->period = AccountingPeriod::create([
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

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    $this->cropCategory = FarmTypeCategory::create(['name' => 'Crop']);
    $this->livestockCategory = FarmTypeCategory::create(['name' => 'Livestock']);
});

function recordFor(FarmerProfile $profile, TransactionTemplate $template, int $amount, string $date, AccountingPeriod $period): Transaction
{
    return Transaction::create([
        'farmer_profile_id' => $profile->id,
        'transaction_template_id' => $template->id,
        'transaction_type' => $template->transaction_type,
        'accounting_period_id' => $period->id,
        'transaction_date' => $date,
        'amount_minor' => $amount,
        'settlement_account_id' => $template->transaction_type === 'INCOME'
            ? $template->debit_account_id
            : $template->credit_account_id,
        'channel' => 'web',
        'recorded_by' => $profile->user_id,
        'posted_at' => now(),
    ]);
}

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
            ->where('livestock_count', '0.00')
            ->where('crop_unit_count', 0));
});

test('shows income, expense and net profit for the last 30 days by default', function () {
    recordFor($this->profile, $this->incomeTemplate, 50000, now()->toDateString(), $this->period);
    recordFor($this->profile, $this->expenseTemplate, 20000, now()->toDateString(), $this->period);
    recordFor($this->profile, $this->incomeTemplate, 100000, now()->subDays(40)->toDateString(), $this->period);

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
    recordFor($other, $this->incomeTemplate, 999999, now()->toDateString(), $this->period);

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->where('summary.total_income', 0));
});

test('income trend is up and good when income rose vs the previous period', function () {
    recordFor($this->profile, $this->incomeTemplate, 25000, now()->subDays(45)->toDateString(), $this->period);
    recordFor($this->profile, $this->incomeTemplate, 50000, now()->subDays(5)->toDateString(), $this->period);

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.income.direction', 'up')
            ->where('summary.trends.income.percent', 100)
            ->where('summary.trends.income.good', true));
});

test('expense trend is down and good when expenses fell vs the previous period', function () {
    recordFor($this->profile, $this->expenseTemplate, 40000, now()->subDays(45)->toDateString(), $this->period);
    recordFor($this->profile, $this->expenseTemplate, 20000, now()->subDays(5)->toDateString(), $this->period);

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.expense.direction', 'down')
            ->where('summary.trends.expense.percent', 50)
            ->where('summary.trends.expense.good', true));
});

test('expense trend is up and bad when expenses rose vs the previous period', function () {
    recordFor($this->profile, $this->expenseTemplate, 10000, now()->subDays(45)->toDateString(), $this->period);
    recordFor($this->profile, $this->expenseTemplate, 30000, now()->subDays(5)->toDateString(), $this->period);

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.expense.direction', 'up')
            ->where('summary.trends.expense.good', false));
});

test('percent is null when there is nothing to compare against in the previous period', function () {
    recordFor($this->profile, $this->incomeTemplate, 50000, now()->subDays(5)->toDateString(), $this->period);

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.income.direction', 'up')
            ->where('summary.trends.income.percent', null));
});

test('net trend reflects income minus expense movement, not just income', function () {
    // previous period: income 100, expense 20 -> net 80
    recordFor($this->profile, $this->incomeTemplate, 10000, now()->subDays(45)->toDateString(), $this->period);
    recordFor($this->profile, $this->expenseTemplate, 2000, now()->subDays(45)->toDateString(), $this->period);
    // current period: income 100, expense 80 -> net 20 (net fell, though income unchanged)
    recordFor($this->profile, $this->incomeTemplate, 10000, now()->subDays(5)->toDateString(), $this->period);
    recordFor($this->profile, $this->expenseTemplate, 8000, now()->subDays(5)->toDateString(), $this->period);

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
        ->assertInertia(fn($page) => $page
            ->where('summary.trends.net.direction', 'down')
            ->where('summary.trends.net.good', false));
});

test('a custom date range can be requested', function () {
    recordFor($this->profile, $this->incomeTemplate, 70000, now()->subDays(90)->toDateString(), $this->period);

    $this->actingAs($this->farmerUser)
        ->get('/farmer/dashboard?from=' . now()->subDays(100)->toDateString() . '&to=' . now()->subDays(80)->toDateString())
        ->assertInertia(fn($page) => $page->where('summary.total_income', 70000));
});

test('counts livestock across the farmer\'s approved, confirmed livestock stock', function () {
    $livestockType = FarmType::create(['name' => 'Layers', 'category_id' => $this->livestockCategory->id]);
    $unit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $livestockType->id,
    ]);
    FarmUnitStock::factory()->confirmed()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 50]);

    // unconfirmed stock should not count
    $unconfirmedUnit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $livestockType->id,
    ]);
    FarmUnitStock::factory()->create(['farm_unit_id' => $unconfirmedUnit->id, 'opening_quantity' => 999]);

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->where('livestock_count', '50.00'));
});

test('counts the farmer\'s approved crop units', function () {
    $cropType = FarmType::create(['name' => 'Maize', 'category_id' => $this->cropCategory->id]);
    FarmUnit::factory()->approved()->count(2)->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $cropType->id,
    ]);

    // unapproved unit should not count
    FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $cropType->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->where('crop_unit_count', 2));
});
