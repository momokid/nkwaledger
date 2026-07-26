<?php

use App\Models\FarmTypeCategory;
use Illuminate\Database\QueryException;

test('a farm type category can be created', function () {
    $category = FarmTypeCategory::create([
        'name' => 'Crop',
    ]);

    expect($category->name)->toBe('Crop');
    expect($category->is_active)->toBeTrue();
});

test('farm type category name must be unique', function () {
    FarmTypeCategory::create(['name' => 'Livestock']);

    expect(fn() => FarmTypeCategory::create(['name' => 'Livestock']))
        ->toThrow(QueryException::class);
});

test('a farm type category can be deactivated without being deleted', function () {
    $category = FarmTypeCategory::create(['name' => 'Mixed']);

    $category->update(['is_active' => false]);

    expect($category->fresh()->is_active)->toBeFalse();
    expect(FarmTypeCategory::find($category->id))->not->toBeNull();
});

test('a farm type category can be soft deleted', function () {
    $category = FarmTypeCategory::create(['name' => 'Aquatic']);

    $category->delete();

    expect(FarmTypeCategory::find($category->id))->toBeNull();
    expect(FarmTypeCategory::withTrashed()->find($category->id))->not->toBeNull();
});
