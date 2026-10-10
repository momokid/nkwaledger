<?php

// cancelling a record that moved stock puts the stock back (or takes bought stock out again).

use App\Enums\MovementReason;
use App\Exceptions\Ledger\PostingFailed;
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
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\ReversalService;
use App\Services\Ledger\StockReversal;

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

    $cash = $account('Cash A/C', $assetSub->id, true);
    $sales = $account('Sales A/C', $incomeSub->id);
    $lossAccount = $account('Loss A/C', $expenseSub->id);
    $livestock = $account('Livestock A/C', $assetSub->id);
    $this->cash = $cash;

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

    $this->produceSale = $template('produce_sale', 'INCOME', $cash->id, $sales->id, 'debit', ['is_produce_sale' => true]);
    $this->lossTemplate = $template('unit_loss', 'LOSS', $lossAccount->id, $livestock->id, 'none');
    $this->purchase = $template('animal_purchase', 'EXPENSE', $livestock->id, $cash->id, 'credit', ['is_stock_purchase' => true, 'stock_source' => 'purchase']);
    $template('correction', 'ADJUSTMENT', $cash->id, $sales->id, 'none', ['requires_farm_unit' => false]);

    $this->clerk = User::factory()->create();
    $this->approver = User::factory()->create();
    $this->profile = FarmerProfile::factory()->create();

    $this->unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);
    $this->stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 100]);

    $posting = app(PostingService::class);

    $this->put = function (TransactionTemplate $template, string $amount, array $quantities) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $this->profile->id,
            transactionTemplateId: $template->id,
            amount: $amount,
            settlementAccountId: $template->settlement_side === 'none' ? null : $this->cash->id,
            transactionDate: now()->toDateString(),
            farmUnitId: $this->unit->id,
            recordedBy: $this->clerk->id,
            quantityLost: $quantities['lost'] ?? null,
            quantitySold: $quantities['sold'] ?? null,
            quantityPurchased: $quantities['bought'] ?? null,
            unitOfMeasure: 'birds',
        ));
    };

    $this->cancel = function (Transaction $transaction) {
        $service = app(ReversalService::class);
        $request = $service->request($transaction, $this->clerk, 'Typed wrongly');
        $service->approve($request, $this->approver);
    };

    $this->onHand = fn() => (float) FarmUnitStock::where('farm_unit_id', $this->unit->id)->sum('current_quantity');
});

test('control: the unit starts with 100 and a sale of 10 takes it to 90', function () {
    ($this->put)($this->produceSale, '500', ['sold' => '10']);

    expect(($this->onHand)())->toBe(90.0);
});

test('cancelling a produce sale puts the stock back', function () {
    $sale = ($this->put)($this->produceSale, '500', ['sold' => '10']);
    $afterSale = ($this->onHand)();

    ($this->cancel)($sale);

    expect(['after_sale' => $afterSale, 'after_cancel' => ($this->onHand)()])
        ->toBe(['after_sale' => 90.0, 'after_cancel' => 100.0]);
});

test('cancelling a loss puts the stock back', function () {
    $loss = ($this->put)($this->lossTemplate, '300', ['lost' => '5']);
    $afterLoss = ($this->onHand)();

    ($this->cancel)($loss);

    expect(['after_loss' => $afterLoss, 'after_cancel' => ($this->onHand)()])
        ->toBe(['after_loss' => 95.0, 'after_cancel' => 100.0]);
});

// a purchase only counts once somebody else confirms it, so it is confirmed here before cancelling
test('cancelling a confirmed purchase takes the bought stock back out', function () {
    $purchase = ($this->put)($this->purchase, '800', ['bought' => '20']);

    FarmUnitStockMovement::where('reason', MovementReason::Purchase)->get()
        ->each(fn($movement) => $movement->forceFill(['confirmed_at' => now(), 'confirmed_by' => $this->approver->id])->save());
    $afterPurchase = ($this->onHand)();

    ($this->cancel)($purchase);

    expect(['after_purchase' => $afterPurchase, 'after_cancel' => ($this->onHand)()])
        ->toBe(['after_purchase' => 120.0, 'after_cancel' => 100.0]);
});

// the same purchase cancelled before anyone confirmed it: confirming afterwards must not add stock
test('a cancelled purchase that is confirmed afterwards still adds no stock', function () {
    $purchase = ($this->put)($this->purchase, '800', ['bought' => '20']);

    ($this->cancel)($purchase);

    FarmUnitStockMovement::where('reason', MovementReason::Purchase)->get()
        ->each(fn($movement) => $movement->forceFill(['confirmed_at' => now(), 'confirmed_by' => $this->approver->id])->save());

    expect(($this->onHand)())->toBe(100.0);
});

test('a stock movement can be traced to the transaction that made it', function () {
    $sale = ($this->put)($this->produceSale, '500', ['sold' => '10']);
    $purchase = ($this->put)($this->purchase, '800', ['bought' => '20']);

    expect([
        'sale' => FarmUnitStockMovement::where('reason', MovementReason::Sale)->value('transaction_id'),
        'purchase' => FarmUnitStockMovement::where('reason', MovementReason::Purchase)->value('transaction_id'),
    ])->toBe(['sale' => $sale->id, 'purchase' => $purchase->id]);
});

test('cancelling one sale leaves the stock of another sale alone', function () {
    $first = ($this->put)($this->produceSale, '500', ['sold' => '10']);
    ($this->put)($this->produceSale, '200', ['sold' => '5']);

    ($this->cancel)($first);

    expect(($this->onHand)())->toBe(95.0);
});

test('cancelling a sale spread over several batches puts each batch back', function () {
    $second = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'opening_quantity' => 100,
        'started_on' => now()->subMonth(),
    ]);

    $sale = ($this->put)($this->produceSale, '500', ['sold' => '40']);
    $afterSale = ($this->onHand)();

    ($this->cancel)($sale);

    expect([
        'after_sale' => $afterSale,
        'first_batch' => (float) $this->stock->fresh()->current_quantity,
        'second_batch' => (float) $second->fresh()->current_quantity,
    ])->toBe(['after_sale' => 160.0, 'first_batch' => 100.0, 'second_batch' => 100.0]);
});

test('a purchase whose stock has already been used cannot be cancelled', function () {
    $purchase = ($this->put)($this->purchase, '800', ['bought' => '20']);

    FarmUnitStockMovement::where('reason', MovementReason::Purchase)->get()
        ->each(fn($movement) => $movement->forceFill(['confirmed_at' => now(), 'confirmed_by' => $this->approver->id])->save());

    // 110 of the 120 on hand is sold, so only 10 is left and 20 cannot come back out
    ($this->put)($this->produceSale, '900', ['sold' => '110']);

    $refusal = null;

    try {
        ($this->cancel)($purchase);
    } catch (PostingFailed $failure) {
        $refusal = $failure->getMessage();
    }

    expect([
        'refusal' => $refusal,
        'on_hand' => ($this->onHand)(),
        'cancelled' => $purchase->reversedBy()->exists(),
    ])->toBe([
        'refusal' => StockReversal::STOCK_USED,
        'on_hand' => 10.0,
        'cancelled' => false,
    ]);
});

test('a purchase that created a brand new batch: cancelling before it is checked ends the empty batch', function () {
    FarmUnitStock::query()->delete();

    $purchase = ($this->put)($this->purchase, '800', ['bought' => '20']);
    $batch = FarmUnitStock::where('farm_unit_id', $this->unit->id)->first();
    $movement = FarmUnitStockMovement::where('transaction_id', $purchase->id)->first();

    ($this->cancel)($purchase);

    // the batch is ended, not rejected or deleted, so its history stays
    expect([
        'movement_traced' => $movement !== null,
        'movement_rejected' => $movement->fresh()->isRejected(),
        'batch_count' => (float) $batch->fresh()->current_quantity,
        'batch_rejected' => $batch->fresh()->isRejected(),
        'batch_ended' => $batch->fresh()->ended_on !== null,
    ])->toBe([
        'movement_traced' => true,
        'movement_rejected' => true,
        'batch_count' => 0.0,
        'batch_rejected' => false,
        'batch_ended' => true,
    ]);
});

test('a purchase that created a brand new batch: cancelling after it was checked empties and ends the batch', function () {
    FarmUnitStock::query()->delete();

    $purchase = ($this->put)($this->purchase, '800', ['bought' => '20']);
    $batch = FarmUnitStock::where('farm_unit_id', $this->unit->id)->first();

    FarmUnitStockMovement::where('transaction_id', $purchase->id)->get()
        ->each(fn($movement) => $movement->forceFill(['confirmed_at' => now(), 'confirmed_by' => $this->approver->id])->save());
    $afterCheck = (float) $batch->fresh()->current_quantity;

    ($this->cancel)($purchase);

    expect([
        'after_check' => $afterCheck,
        'after_cancel' => (float) $batch->fresh()->current_quantity,
        'batch_rejected' => $batch->fresh()->isRejected(),
        'batch_ended' => $batch->fresh()->ended_on !== null,
    ])->toBe(['after_check' => 20.0, 'after_cancel' => 0.0, 'batch_rejected' => false, 'batch_ended' => true]);
});

test('an ended batch is no longer an active batch of the farm unit, and keeps its history', function () {
    FarmUnitStock::query()->delete();

    $purchase = ($this->put)($this->purchase, '800', ['bought' => '20']);
    ($this->cancel)($purchase);

    $batch = FarmUnitStock::where('farm_unit_id', $this->unit->id)->first();

    expect([
        'active_batches' => FarmUnitStock::where('farm_unit_id', $this->unit->id)->whereNull('ended_on')->count(),
        'ended_on' => $batch->ended_on->toDateString(),
        'still_has_its_movement' => $batch->movements()->count(),
    ])->toBe(['active_batches' => 0, 'ended_on' => now()->toDateString(), 'still_has_its_movement' => 1]);
});

test('cancelling a purchase that added to an existing batch leaves that batch active', function () {
    $purchase = ($this->put)($this->purchase, '800', ['bought' => '20']);

    ($this->cancel)($purchase);

    expect([
        'ended' => $this->stock->fresh()->ended_on !== null,
        'on_hand' => ($this->onHand)(),
    ])->toBe(['ended' => false, 'on_hand' => 100.0]);
});

test('a new batch with another movement on it is not ended when the first purchase is cancelled', function () {
    FarmUnitStock::query()->delete();

    $first = ($this->put)($this->purchase, '800', ['bought' => '20']);
    ($this->put)($this->purchase, '100', ['bought' => '5']);
    $batch = FarmUnitStock::where('farm_unit_id', $this->unit->id)->first();

    ($this->cancel)($first);

    expect($batch->fresh()->ended_on)->toBeNull();
});

test('a new batch that still holds stock is not ended when the first purchase is cancelled', function () {
    FarmUnitStock::query()->delete();

    $first = ($this->put)($this->purchase, '800', ['bought' => '20']);
    $second = ($this->put)($this->purchase, '100', ['bought' => '5']);
    $batch = FarmUnitStock::where('farm_unit_id', $this->unit->id)->first();

    FarmUnitStockMovement::whereIn('transaction_id', [$first->id, $second->id])->get()
        ->each(fn($movement) => $movement->forceFill(['confirmed_at' => now(), 'confirmed_by' => $this->approver->id])->save());

    ($this->cancel)($first);

    expect([
        'ended' => $batch->fresh()->ended_on !== null,
        'left' => (float) $batch->fresh()->current_quantity,
    ])->toBe(['ended' => false, 'left' => 5.0]);
});
