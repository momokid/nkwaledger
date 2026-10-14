<?php

// edges in how a sale or loss is spread across batches, and fractional quantities.

use App\Enums\MovementReason;
use App\Exceptions\Ledger\PostingFailed;
use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
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

beforeEach(function () {
    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id]);
    $expenses = LedgerCategory::create(['name' => 'Expenses', 'class_id' => $dr->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => $expenses->id, 'name' => 'Farm Expenses']);

    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name,
        'control_id' => $control->id,
        'subcategory_id' => $sub,
        'type_id' => $type->id,
        'is_settlement' => $settlement,
    ]);

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $sales = $account('Sales A/C', $incomeSub->id);
    $lossAccount = $account('Loss A/C', $expenseSub->id);
    $livestock = $account('Livestock A/C', $assetSub->id);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $template = fn(string $slug, string $type, int $dr, int $cr, string $side, array $extra = []) => TransactionTemplate::create([
        'name' => $slug,
        'slug' => $slug,
        'transaction_type' => $type,
        'debit_account_id' => $dr,
        'credit_account_id' => $cr,
        'settlement_side' => $side,
        'requires_farm_unit' => true,
    ] + $extra);

    $this->produceSale = $template('produce_sale', 'INCOME', $this->cash->id, $sales->id, 'debit', ['is_produce_sale' => true]);
    $this->lossTemplate = $template('unit_loss', 'LOSS', $lossAccount->id, $livestock->id, 'none');
    $this->purchase = $template('animal_purchase', 'EXPENSE', $livestock->id, $this->cash->id, 'credit', ['is_stock_purchase' => true, 'stock_source' => 'purchase']);

    $this->clerk = User::factory()->create();
    $this->profile = FarmerProfile::factory()->create();

    $this->unitOf = fn(bool $decimal) => FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => FarmType::factory()->withCategory()->create(['quantity_is_decimal' => $decimal])->id,
    ]);

    $posting = app(PostingService::class);

    $this->put = function (FarmUnit $unit, TransactionTemplate $template, string $quantity) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $this->profile->id,
            transactionTemplateId: $template->id,
            amount: '100',
            settlementAccountId: $template->settlement_side === 'none' ? null : $this->cash->id,
            transactionDate: now()->toDateString(),
            farmUnitId: $unit->id,
            recordedBy: $this->clerk->id,
            quantityLost: $template->transaction_type === 'LOSS' ? $quantity : null,
            quantitySold: $template->is_produce_sale ? $quantity : null,
            quantityPurchased: $template->is_stock_purchase ? $quantity : null,
            unitOfMeasure: 'birds',
        ));
    };

    // three batches of 10, then (optionally) a newest batch with nothing on hand
    $this->batches = function (FarmUnit $unit, bool $withEmptyLast) {
        foreach ([3, 2, 1] as $monthsAgo) {
            FarmUnitStock::factory()->create([
                'farm_unit_id' => $unit->id,
                'opening_quantity' => 10,
                'started_on' => now()->subMonths($monthsAgo),
            ]);
        }

        if ($withEmptyLast) {
            // an unchecked opening count does not count, so this batch is live but holds 0
            FarmUnitStock::factory()->pendingOpening()->create([
                'farm_unit_id' => $unit->id,
                'opening_quantity' => 10,
                'started_on' => now()->subDay(),
            ]);
        }
    };

    $this->onHand = fn(FarmUnit $unit) => round((float) FarmUnitStock::where('farm_unit_id', $unit->id)->sum('current_quantity'), 2);

    $this->taken = fn(FarmUnit $unit, MovementReason $reason) => round((float) FarmUnitStockMovement::query()
        ->whereIn('farm_unit_stock_id', FarmUnitStock::where('farm_unit_id', $unit->id)->pluck('id'))
        ->where('reason', $reason)
        ->sum('quantity'), 2);

    $this->movesOnEmptyBatch = fn(FarmUnit $unit, MovementReason $reason) => FarmUnitStockMovement::query()
        ->whereIn('farm_unit_stock_id', FarmUnitStock::where('farm_unit_id', $unit->id)->where('current_quantity', 0)->pluck('id'))
        ->where('reason', $reason)
        ->count();
});

test('control: with no empty batch a sale of 1 across three batches of 10 reduces exactly 1', function () {
    $unit = ($this->unitOf)(true);
    ($this->batches)($unit, false);

    ($this->put)($unit, $this->produceSale, '1');

    expect([
        'sold' => ($this->taken)($unit, MovementReason::Sale),
        'on_hand' => ($this->onHand)($unit),
    ])->toBe(['sold' => 1.0, 'on_hand' => 29.0]);
});

test('a sale whose rounding remainder falls on an empty last batch still reduces exactly the quantity sold', function () {
    $unit = ($this->unitOf)(true);
    ($this->batches)($unit, true);

    ($this->put)($unit, $this->produceSale, '1');

    expect([
        'sold' => ($this->taken)($unit, MovementReason::Sale),
        'on_hand' => ($this->onHand)($unit),
        'sale_movements_on_the_empty_batch' => ($this->movesOnEmptyBatch)($unit, MovementReason::Sale),
    ])->toBe(['sold' => 1.0, 'on_hand' => 29.0, 'sale_movements_on_the_empty_batch' => 0]);
});

test('a loss whose rounding remainder falls on an empty last batch still reduces exactly the quantity lost', function () {
    $unit = ($this->unitOf)(true);
    ($this->batches)($unit, true);

    ($this->put)($unit, $this->lossTemplate, '1');

    expect([
        'lost' => ($this->taken)($unit, MovementReason::Loss),
        'on_hand' => ($this->onHand)($unit),
        'loss_movements_on_the_empty_batch' => ($this->movesOnEmptyBatch)($unit, MovementReason::Loss),
    ])->toBe(['lost' => 1.0, 'on_hand' => 29.0, 'loss_movements_on_the_empty_batch' => 0]);
});

test('control: a unit whose type allows decimals accepts 2.5 sold', function () {
    $unit = ($this->unitOf)(true);
    ($this->batches)($unit, false);

    ($this->put)($unit, $this->produceSale, '2.5');

    expect(($this->onHand)($unit))->toBe(27.5);
});

test('a fractional quantity sold is refused when the farm type does not allow decimals', function () {
    $unit = ($this->unitOf)(false);
    ($this->batches)($unit, false);

    expect(fn() => ($this->put)($unit, $this->produceSale, '2.5'))->toThrow(PostingFailed::class);
});

test('a fractional quantity lost is refused when the farm type does not allow decimals', function () {
    $unit = ($this->unitOf)(false);
    ($this->batches)($unit, false);

    expect(fn() => ($this->put)($unit, $this->lossTemplate, '2.5'))->toThrow(PostingFailed::class);
});

test('a fractional quantity bought is refused when the farm type does not allow decimals', function () {
    $unit = ($this->unitOf)(false);
    ($this->batches)($unit, false);

    expect(fn() => ($this->put)($unit, $this->purchase, '2.5'))->toThrow(PostingFailed::class);
});

test('a tiny sale spread over five batches still adds up to exactly what was sold', function () {
    $unit = ($this->unitOf)(true);

    foreach ([5, 4, 3, 2, 1] as $monthsAgo) {
        FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 10, 'started_on' => now()->subMonths($monthsAgo)]);
    }

    ($this->put)($unit, $this->produceSale, '0.03');

    expect([
        'sold' => ($this->taken)($unit, MovementReason::Sale),
        'on_hand' => ($this->onHand)($unit),
        'lowest_batch' => (float) FarmUnitStock::where('farm_unit_id', $unit->id)->min('current_quantity'),
    ])->toBe(['sold' => 0.03, 'on_hand' => 49.97, 'lowest_batch' => 9.99]);
});

test('selling everything leaves every batch at exactly zero', function () {
    $unit = ($this->unitOf)(true);
    ($this->batches)($unit, true);

    ($this->put)($unit, $this->produceSale, '30');

    expect([
        'on_hand' => ($this->onHand)($unit),
        'sold' => ($this->taken)($unit, MovementReason::Sale),
    ])->toBe(['on_hand' => 0.0, 'sold' => 30.0]);
});
