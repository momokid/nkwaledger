<?php

use App\Enums\StockSource;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\User;

test('a stock belongs to a farm unit', function () {
    $unit = FarmUnit::factory()->approved()->create();
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id]);

    expect($stock->farmUnit->id)->toBe($unit->id);
});

test('a unit can hold many stocks', function () {
    $unit = FarmUnit::factory()->approved()->create();
    FarmUnitStock::factory()->count(3)->create(['farm_unit_id' => $unit->id]);

    expect($unit->fresh()->stocks)->toHaveCount(3);
});

// the farmer is never blocked, the entry just does not count yet
test('a stock can be added to an unapproved unit', function () {
    $unit = FarmUnit::factory()->create();
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id]);

    expect($stock->exists)->toBeTrue();
});

test('a unit can hold two open stocks at once', function () {
    $unit = FarmUnit::factory()->approved()->create();

    FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'ended_on' => null]);
    $second = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'ended_on' => null]);

    expect($second->exists)->toBeTrue();
});

test('the source casts to the enum', function () {
    $stock = FarmUnitStock::factory()->create(['source' => StockSource::OpeningBalance]);

    expect($stock->fresh()->source)->toBe(StockSource::OpeningBalance);
});

test('a purchased stock is not an opening balance', function () {
    $stock = FarmUnitStock::factory()->create(['source' => StockSource::Purchase]);

    expect($stock->isOpeningBalance())->toBeFalse();
});

test('an opening balance reports itself as one', function () {
    $stock = FarmUnitStock::factory()->create(['source' => StockSource::OpeningBalance]);

    expect($stock->isOpeningBalance())->toBeTrue();
});

// a new batch starts with everything it came with
test('current quantity starts equal to opening quantity', function () {
    $stock = FarmUnitStock::factory()->create([
        'opening_quantity' => 200,
        'current_quantity' => null,
    ]);

    expect($stock->current_quantity)->toBe('200.00');
});

test('a stock holds its acquisition cost', function () {
    $stock = FarmUnitStock::factory()->create(['acquisition_cost' => 4000]);

    expect($stock->acquisition_cost)->toBe('4000.00');
});

// what the farmer paid is spread across what is alive today, not what was bought
test('cost per unit divides the cost across the current quantity', function () {
    $stock = FarmUnitStock::factory()->create([
        'opening_quantity' => 200,
        'current_quantity' => 160,
        'acquisition_cost' => 4000,
    ]);

    expect($stock->costPerUnit())->toBe('25.00');
});

test('cost per unit is null when nothing is left', function () {
    $stock = FarmUnitStock::factory()->create(['current_quantity' => 0, 'acquisition_cost' => 4000]);

    expect($stock->costPerUnit())->toBeNull();
});

test('a stock with no cost is allowed', function () {
    $stock = FarmUnitStock::factory()->create(['acquisition_cost' => 0]);

    expect($stock->costPerUnit())->toBe('0.00');
});

test('a stock records who recorded it', function () {
    $agent = User::factory()->create();
    $stock = FarmUnitStock::factory()->create(['recorded_by' => $agent->id]);

    expect($stock->recordedBy->id)->toBe($agent->id);
});

test('a new stock is not confirmed', function () {
    expect(FarmUnitStock::factory()->create()->isConfirmed())->toBeFalse();
});

test('a confirmed stock reports itself confirmed', function () {
    expect(FarmUnitStock::factory()->confirmed()->create()->isConfirmed())->toBeTrue();
});

test('a confirmed stock records who confirmed it and when', function () {
    $stock = FarmUnitStock::factory()->confirmed()->create();

    expect($stock->confirmed_by)->not->toBeNull()
        ->and($stock->confirmed_at)->not->toBeNull();
});

// whoever wrote the number down is not the one who checks it
test('the recorder is the conflicted party for confirmation', function () {
    $agent = User::factory()->create();
    $stock = FarmUnitStock::factory()->create(['recorded_by' => $agent->id]);

    expect($stock->conflictedUserId())->toBe($agent->id);
});

test('the confirmed scope returns only confirmed stocks', function () {
    FarmUnitStock::factory()->count(2)->create();
    FarmUnitStock::factory()->confirmed()->create();

    expect(FarmUnitStock::confirmed()->count())->toBe(1);
});

// both checks must pass, since a checked number in an unchecked pen proves nothing
test('a confirmed stock in an approved unit counts toward credit', function () {
    $unit = FarmUnit::factory()->approved()->create();
    $stock = FarmUnitStock::factory()->confirmed()->create(['farm_unit_id' => $unit->id]);

    expect($stock->countsTowardCredit())->toBeTrue();
});

test('a confirmed stock in an unapproved unit does not count toward credit', function () {
    $unit = FarmUnit::factory()->create();
    $stock = FarmUnitStock::factory()->confirmed()->create(['farm_unit_id' => $unit->id]);

    expect($stock->countsTowardCredit())->toBeFalse();
});

test('an unconfirmed stock in an approved unit does not count toward credit', function () {
    $unit = FarmUnit::factory()->approved()->create();
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id]);

    expect($stock->countsTowardCredit())->toBeFalse();
});

// a herd never closes, so an end date is the exception
test('a stock with no end date is still open', function () {
    expect(FarmUnitStock::factory()->create(['ended_on' => null])->isOpen())->toBeTrue();
});

test('a stock with an end date is closed', function () {
    expect(FarmUnitStock::factory()->create(['ended_on' => now()])->isOpen())->toBeFalse();
});

test('dates cast to date instances', function () {
    $stock = FarmUnitStock::factory()->create(['started_on' => '2026-03-01']);

    expect($stock->started_on)->toBeInstanceOf(Carbon\CarbonInterface::class);
});

test('a deleted stock is soft deleted', function () {
    $stock = FarmUnitStock::factory()->create();

    $stock->delete();

    expect(FarmUnitStock::find($stock->id))->toBeNull()
        ->and(FarmUnitStock::withTrashed()->find($stock->id))->not->toBeNull();
});
