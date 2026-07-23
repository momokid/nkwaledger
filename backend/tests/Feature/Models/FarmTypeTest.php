<?php

use App\Models\FarmType;
use Illuminate\Database\QueryException;

test('a farm type can be created with a crop category', function () {
    $farmType = FarmType::create([
        'name' => 'Maize',
        'category' => 'crop',
    ]);

    expect($farmType->name)->toBe('Maize');
    expect($farmType->category)->toBe('crop');
    expect($farmType->is_active)->toBeTrue();
});

test('a farm type can be created with a livestock category', function () {
    $farmType = FarmType::create([
        'name' => 'Poultry',
        'category' => 'livestock',
    ]);

    expect($farmType->category)->toBe('livestock');
});

test('farm type name must be unique', function () {
    FarmType::create(['name' => 'Cassava', 'category' => 'crop']);

    expect(fn() => FarmType::create(['name' => 'Cassava', 'category' => 'crop']))
        ->toThrow(QueryException::class);
});

test('farm type category must be crop or livestock', function () {
    expect(fn() => FarmType::create(['name' => 'Fish', 'category' => 'aquatic']))
        ->toThrow(InvalidArgumentException::class);
});

test('a farm type can be deactivated without being deleted', function () {
    $farmType = FarmType::create(['name' => 'Cattle', 'category' => 'livestock']);

    $farmType->update(['is_active' => false]);

    expect($farmType->fresh()->is_active)->toBeFalse();
    expect(FarmType::find($farmType->id))->not->toBeNull();
});

test('a farm type can be soft deleted', function () {
    $farmType = FarmType::create(['name' => 'Goats', 'category' => 'livestock']);

    $farmType->delete();

    expect(FarmType::find($farmType->id))->toBeNull();
    expect(FarmType::withTrashed()->find($farmType->id))->not->toBeNull();
});
