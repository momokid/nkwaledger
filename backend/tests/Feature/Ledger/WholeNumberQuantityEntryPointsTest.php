<?php

// a fractional quantity is refused for a farm type counted in whole numbers, on the web form
// and on the sync path (posting itself is covered in StockAllocationEdgesTest)

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmType;
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
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);
    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $control->id, 'subcategory_id' => $sub, 'type_id' => $type->id, 'is_settlement' => $settlement,
    ]);

    $this->cash = $account('Cash', $assetSub->id, true);
    $sales = $account('Sales', $incomeSub->id);

    $this->produceSale = TransactionTemplate::create([
        'name' => 'I sold produce', 'slug' => 'produce_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
        'requires_farm_unit' => true, 'is_produce_sale' => true,
    ]);

    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    $this->unit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => FarmType::factory()->withCategory()->create(['quantity_is_decimal' => false])->id,
    ]);
    FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 100]);

    $this->webSale = fn(string $quantity) => $this->actingAs($this->farmerUser)->post('/my-records', [
        'transaction_template_id' => $this->produceSale->id,
        'amount' => '100',
        'settlement_account_id' => $this->cash->id,
        'transaction_date' => now()->toDateString(),
        'farm_unit_id' => $this->unit->id,
        'quantity_sold' => $quantity,
    ]);

    $this->syncSale = fn(string $quantity) => $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [[
        'uuid' => (string) Str::uuid(),
        'template' => $this->produceSale->id,
        'farmer' => $this->profile->uuid,
        'amount' => '100',
        'settlement_account_id' => $this->cash->id,
        'farm_unit_id' => $this->unit->id,
        'quantity' => $quantity,
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ]]])->assertOk()->json('results.0');
});

test('the web form shows an error under the quantity for a fraction on a whole-number farm', function () {
    ($this->webSale)('2.5')->assertSessionHasErrors(['quantity_sold' => FarmUnit::WHOLE_NUMBERS_ONLY]);

    expect(Transaction::count())->toBe(0);
});

test('control: the web form accepts a whole number on the same farm', function () {
    ($this->webSale)('2')->assertSessionHasNoErrors();

    expect(Transaction::count())->toBe(1);
});

test('sync sends a fraction on a whole-number farm to needs fixing with the same words', function () {
    $result = ($this->syncSale)('2.5');

    expect([$result['status'], $result['reason'], Transaction::count()])
        ->toBe(['needs_fixing', FarmUnit::WHOLE_NUMBERS_ONLY, 0]);
});

test('control: sync accepts a whole number on the same farm', function () {
    expect(($this->syncSale)('2')['status'])->toBe('accepted');
});
