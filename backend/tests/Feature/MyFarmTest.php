<?php

use App\Enums\MovementReason;
use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
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
use App\Enums\StockSource;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

test('a guest is redirected to login', function () {
    $this->get('/my-farm')->assertRedirect('/login');
});

test('a farmer sees their own farm units', function () {
    $unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);
    FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id]);

    $other = FarmerProfile::factory()->create();
    $otherUnit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $other->id]);
    FarmUnitStock::factory()->create(['farm_unit_id' => $otherUnit->id]);

    $this->actingAs($this->farmerUser)->get('/my-farm')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('MyFarm/Index')
            ->has('units', 1)
            ->where('units.0.id', $unit->id));
});

test('a farmer with no profile is forbidden', function () {
    $bare = User::factory()->create();
    $bare->assignRole('farmer');

    $this->actingAs($bare)->get('/my-farm')->assertForbidden();
});

test('a user without the farm-units permission is forbidden', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/my-farm')->assertForbidden();
});

// the starting count anchors the story at the top, no matter how old it is
test('the starting count comes before later movements regardless of date', function () {
    $unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);

    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $unit->id,
        'opening_quantity' => 29,
        'started_on' => '2025-01-01',
    ]);

    $stock->movements()->create([
        'reason' => MovementReason::Loss,
        'quantity' => 3,
        'is_increase' => false,
        'occurred_on' => now(),
        'recorded_by' => $this->farmerUser->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-farm')
        ->assertInertia(fn($page) => $page
            ->where('units.0.stocks.0.movements.0.reason', 'Starting count')
            ->where('units.0.stocks.0.movements.1.reason', 'Lost'));
});

// green for what was added, amber for what was taken away
test('each movement says which way it went', function () {
    $unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);

    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $unit->id,
        'opening_quantity' => 29,
        'started_on' => now()->subMonth(),
    ]);

    $this->actingAs($this->farmerUser)->get('/my-farm')
        ->assertInertia(fn($page) => $page
            ->where('units.0.stocks.0.movements.0.is_increase', true));
});

// the farmer's own words: starts with, added, bought, lost, sold
test('the timeline merges every batch into one running total', function () {
    $unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);

    $first = FarmUnitStock::factory()->openingBalance()->create([
        'farm_unit_id' => $unit->id,
        'opening_quantity' => 10,
        'started_on' => '2026-01-01',
    ]);

    $second = FarmUnitStock::factory()->openingBalance()->create([
        'farm_unit_id' => $unit->id,
        'opening_quantity' => 14,
        'started_on' => '2026-02-01',
    ]);

    $third = FarmUnitStock::factory()->create([
        'farm_unit_id' => $unit->id,
        'opening_quantity' => 5,
        'started_on' => '2026-03-01',
    ]);

    $third->movements()->create([
        'reason' => MovementReason::Loss,
        'quantity' => 7,
        'is_increase' => false,
        'occurred_on' => '2026-04-01',
        'recorded_by' => $this->farmerUser->id,
    ]);

    $second->movements()->create([
        'reason' => MovementReason::Sale,
        'quantity' => 5,
        'is_increase' => false,
        'occurred_on' => '2026-05-01',
        'recorded_by' => $this->farmerUser->id,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-farm')
        ->assertInertia(fn($page) => $page
            ->where('units.0.timeline.0.label', 'Starts with')
            ->where('units.0.timeline.0.running_total', '10')
            ->where('units.0.timeline.1.label', 'Added')
            ->where('units.0.timeline.1.running_total', '24')
            ->where('units.0.timeline.2.label', 'Bought')
            ->where('units.0.timeline.2.running_total', '29')
            ->where('units.0.timeline.3.label', 'Lost')
            ->where('units.0.timeline.3.running_total', '22')
            ->where('units.0.timeline.4.label', 'Sold')
            ->where('units.0.timeline.4.running_total', '17'));
});

// a rejected entry still shows, but the running total moves on without it
test('a rejected timeline entry is shown but excluded from the running total', function () {
    $unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);

    $first = FarmUnitStock::factory()->openingBalance()->create([
        'farm_unit_id' => $unit->id,
        'opening_quantity' => 10,
        'started_on' => '2026-01-01',
    ]);

    $second = FarmUnitStock::factory()->openingBalance()->create([
        'farm_unit_id' => $unit->id,
        'opening_quantity' => 14,
        'started_on' => '2026-02-01',
    ]);

    $second->reject($this->farmerUser->id, 'Miscounted');

    $this->actingAs($this->farmerUser)->get('/my-farm')
        ->assertInertia(fn($page) => $page
            ->where('units.0.timeline.1.label', 'Added')
            ->where('units.0.timeline.1.is_rejected', true)
            ->where('units.0.timeline.1.running_total', '10'));
});
test('shows profit and loss analysis per farm unit', function () {
    $unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);

    $period = AccountingPeriod::factory()->create([
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->endOfMonth()->toDateString(),
    ]);

    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);
    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id])->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Expenses', 'class_id' => $drClass->id])->id, 'name' => 'Farm Expenses']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $subId, bool $settlement = false) => LedgerAccount::create([
        'name' => $name,
        'control_id' => $control->id,
        'subcategory_id' => $subId,
        'type_id' => $type->id,
        'is_settlement' => $settlement,
    ]);

    $cash = $account('Cash A/C', $assetSub->id, true);
    $sales = $account('Income on Sales', $incomeSub->id);
    $feed = $account('Expense on Feed', $expenseSub->id);
    $lossAccount = $account('Loss on Farm Assets', $expenseSub->id);
    $livestock = $account('Livestock A/C', $assetSub->id);

    $incomeTemplate = TransactionTemplate::create(['name' => 'Sold', 'slug' => 'sold', 'transaction_type' => 'INCOME', 'debit_account_id' => $cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit']);
    $expenseTemplate = TransactionTemplate::create(['name' => 'Fed', 'slug' => 'fed', 'transaction_type' => 'EXPENSE', 'debit_account_id' => $feed->id, 'credit_account_id' => $cash->id, 'settlement_side' => 'credit']);
    $lossTemplate = TransactionTemplate::create(['name' => 'Lost', 'slug' => 'lost', 'transaction_type' => 'LOSS', 'debit_account_id' => $lossAccount->id, 'credit_account_id' => $livestock->id, 'settlement_side' => 'none']);

    $base = [
        'farmer_profile_id' => $this->profile->id,
        'accounting_period_id' => $period->id,
        'transaction_date' => now()->toDateString(),
        'farm_unit_id' => $unit->id,
        'channel' => 'web',
        'recorded_by' => $this->farmerUser->id,
        'posted_at' => now(),
    ];

    Transaction::create($base + ['transaction_template_id' => $incomeTemplate->id, 'transaction_type' => 'INCOME', 'amount_minor' => 50000, 'settlement_account_id' => $cash->id]);
    Transaction::create($base + ['transaction_template_id' => $expenseTemplate->id, 'transaction_type' => 'EXPENSE', 'amount_minor' => 20000, 'settlement_account_id' => $cash->id]);
    Transaction::create($base + ['transaction_template_id' => $lossTemplate->id, 'transaction_type' => 'LOSS', 'amount_minor' => 5000, 'settlement_account_id' => null]);

    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id]);
    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Sale,
        'quantity' => 15,
        'occurred_on' => now(),
    ]);

    $this->actingAs($this->farmerUser)->get('/my-farm')
        ->assertInertia(fn($page) => $page
            ->where('units.0.analysis.total_income', 50000)
            ->where('units.0.analysis.total_expense', 20000)
            ->where('units.0.analysis.total_loss', 5000)
            ->where('units.0.analysis.net', 30000)
            ->where('units.0.analysis.produce_quantity_sold', '15'));
});
