<?php

use App\Enums\MovementReason;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\User;

use InvalidArgumentException;

test('a movement belongs to a stock', function () {
    $stock = FarmUnitStock::factory()->create();
    $movement = FarmUnitStockMovement::factory()->create(['farm_unit_stock_id' => $stock->id]);

    expect($movement->stock->id)->toBe($stock->id);
});

// the sum has to start somewhere, so the first batch writes its own movement
test('creating a stock creates an opening movement', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 200]);

    expect($stock->movements)->toHaveCount(1)
        ->and($stock->movements->first()->reason)->toBe(MovementReason::Opening)
        ->and($stock->movements->first()->quantity)->toBe('200.00');
});

test('a birth adds to the count', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 10]);

    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Birth,
        'quantity' => 2,
    ]);

    expect($stock->fresh()->current_quantity)->toBe('12.00');
});

test('a death takes away from the count', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 200]);

    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Death,
        'quantity' => 40,
    ]);

    expect($stock->fresh()->current_quantity)->toBe('160.00');
});

test('a sale takes away from the count', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 100]);

    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Sale,
        'quantity' => 30,
    ]);

    expect($stock->fresh()->current_quantity)->toBe('70.00');
});

test('many movements add up', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 100]);

    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Birth,
        'quantity' => 20,
    ]);

    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Death,
        'quantity' => 5,
    ]);

    expect($stock->fresh()->current_quantity)->toBe('115.00');
});

// the farmer picks a reason, the direction follows, so it cannot be set the wrong way
test('each reason knows which way it goes', function () {
    expect(MovementReason::Opening->addsToCount())->toBeTrue()
        ->and(MovementReason::Birth->addsToCount())->toBeTrue()
        ->and(MovementReason::Purchase->addsToCount())->toBeTrue()
        ->and(MovementReason::Death->addsToCount())->toBeFalse()
        ->and(MovementReason::Theft->addsToCount())->toBeFalse()
        ->and(MovementReason::Sale->addsToCount())->toBeFalse()
        ->and(MovementReason::Cull->addsToCount())->toBeFalse();
});

// a miscount can go either way, so this one carries its own direction
test('a correction can add or take away', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 100]);

    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Correction,
        'quantity' => 5,
        'is_increase' => false,
    ]);

    expect($stock->fresh()->current_quantity)->toBe('95.00');
});

test('an upward correction adds', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 100]);

    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Correction,
        'quantity' => 5,
        'is_increase' => true,
    ]);

    expect($stock->fresh()->current_quantity)->toBe('105.00');
});

test('the count is rebuilt when a movement is removed', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 100]);

    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Birth,
        'quantity' => 10,
    ]);

    $movement->delete();

    expect($stock->fresh()->current_quantity)->toBe('100.00');
});

test('the count never drops below zero', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 5]);

    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Death,
        'quantity' => 50,
    ]);

    expect($stock->fresh()->current_quantity)->toBe('0.00');
});

test('a movement needs a quantity above zero', function () {
    FarmUnitStockMovement::factory()->create(['quantity' => 0]);
})->throws(InvalidArgumentException::class);

test('a movement records who recorded it', function () {
    $agent = User::factory()->create();
    $movement = FarmUnitStockMovement::factory()->create(['recorded_by' => $agent->id]);

    expect($movement->recordedBy->id)->toBe($agent->id);
});

test('a new movement is not confirmed', function () {
    expect(FarmUnitStockMovement::factory()->create()->isConfirmed())->toBeFalse();
});

test('a confirmed movement reports itself confirmed', function () {
    expect(FarmUnitStockMovement::factory()->confirmed()->create()->isConfirmed())->toBeTrue();
});

test('the recorder is the conflicted party for confirmation', function () {
    $agent = User::factory()->create();
    $movement = FarmUnitStockMovement::factory()->create(['recorded_by' => $agent->id]);

    expect($movement->conflictedUserId())->toBe($agent->id);
});

test('the confirmed scope returns only confirmed movements', function () {
    $stock = FarmUnitStock::factory()->create();

    FarmUnitStockMovement::factory()->create(['farm_unit_stock_id' => $stock->id]);
    FarmUnitStockMovement::factory()->confirmed()->create(['farm_unit_stock_id' => $stock->id]);

    // the stock's own opening movement confirms itself too, so that is 2 confirmed rows here
    expect(FarmUnitStockMovement::confirmed()->count())->toBe(2);
});

// an unchecked number still shows to the farmer, it just proves nothing
test('an unconfirmed movement still changes the count', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 10]);

    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Birth,
        'quantity' => 3,
        'confirmed_at' => null,
    ]);

    expect($stock->fresh()->current_quantity)->toBe('13.00');
});

test('the reason casts to the enum', function () {
    $movement = FarmUnitStockMovement::factory()->create(['reason' => MovementReason::Cull]);

    expect($movement->fresh()->reason)->toBe(MovementReason::Cull);
});

test('the date casts to a date instance', function () {
    $movement = FarmUnitStockMovement::factory()->create(['occurred_on' => '2026-04-10']);

    expect($movement->occurred_on)->toBeInstanceOf(Carbon\CarbonInterface::class);
});

test('a deleted movement is soft deleted', function () {
    $movement = FarmUnitStockMovement::factory()->create();

    $movement->delete();

    expect(FarmUnitStockMovement::find($movement->id))->toBeNull()
        ->and(FarmUnitStockMovement::withTrashed()->find($movement->id))->not->toBeNull();
});

test('a movement can be rejected', function () {
    $movement = FarmUnitStockMovement::factory()->create();

    $movement->reject(2, 'Wrong reason chosen');

    expect($movement->fresh()->isRejected())->toBeTrue();
});

test('a rejected movement stops counting toward the stock total', function () {
    $stock = FarmUnitStock::factory()->create(['opening_quantity' => 100]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Death,
        'quantity' => 20,
    ]);

    $movement->reject(2, 'Never happened');

    expect($stock->fresh()->current_quantity)->toBe('100.00');
});

test('a confirmed movement cannot be rejected', function () {
    $movement = FarmUnitStockMovement::factory()->confirmed()->create();

    expect(fn() => $movement->reject(2, 'Too late'))->toThrow(InvalidArgumentException::class);
});
