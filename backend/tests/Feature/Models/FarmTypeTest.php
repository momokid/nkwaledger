<?php

use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use Illuminate\Database\QueryException;

test('a farm type can be created with a category', function () {
    $category = FarmTypeCategory::create(['name' => 'Crop']);

    $farmType = FarmType::create([
        'name' => 'Maize',
        'category_id' => $category->id,
    ]);

    expect($farmType->name)->toBe('Maize');
    expect($farmType->category_id)->toBe($category->id);
    expect($farmType->is_active)->toBeTrue();
});

// headcount types default to whole numbers unless told otherwise
test('a farm type defaults to whole-number quantities', function () {
    $farmType = FarmType::create(['name' => 'Goats']);

    expect($farmType->quantity_is_decimal)->toBeFalse();
});

test('a farm type can allow decimal quantities, for area or weight-based produce', function () {
    $farmType = FarmType::create(['name' => 'Maize', 'quantity_is_decimal' => true]);

    expect($farmType->fresh()->quantity_is_decimal)->toBeTrue();
});

test('a farm type resolves its category relationship', function () {
    $category = FarmTypeCategory::create(['name' => 'Livestock']);
    $farmType = FarmType::create(['name' => 'Poultry', 'category_id' => $category->id]);

    expect($farmType->category)->not->toBeNull();
    expect($farmType->category->name)->toBe('Livestock');
});

test('a farm type can be created without a category', function () {
    $farmType = FarmType::create(['name' => 'Uncategorized Crop']);

    expect($farmType->category_id)->toBeNull();
    expect($farmType->category)->toBeNull();
});

test('farm type name must be unique', function () {
    FarmType::create(['name' => 'Cassava']);

    expect(fn() => FarmType::create(['name' => 'Cassava']))
        ->toThrow(QueryException::class);
});

test('a farm type can be deactivated without being deleted', function () {
    $farmType = FarmType::create(['name' => 'Cattle']);

    $farmType->update(['is_active' => false]);

    expect($farmType->fresh()->is_active)->toBeFalse();
    expect(FarmType::find($farmType->id))->not->toBeNull();
});

test('a farm type can be soft deleted', function () {
    $farmType = FarmType::create(['name' => 'Goats']);

    $farmType->delete();

    expect(FarmType::find($farmType->id))->toBeNull();
    expect(FarmType::withTrashed()->find($farmType->id))->not->toBeNull();
});

test('a farm type loses its category reference when the category is soft deleted', function () {
    $category = FarmTypeCategory::create(['name' => 'Aquatic']);
    $farmType = FarmType::create(['name' => 'Tilapia', 'category_id' => $category->id]);

    $category->delete();

    expect($farmType->fresh()->category_id)->toBeNull();
});
