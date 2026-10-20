<?php

use App\Models\FarmTypeCategory;
use App\Models\HealthCategory;
use Database\Seeders\HealthCategorySeeder;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->crop = FarmTypeCategory::create(['name' => 'Crop']);
});

test('a health category belongs to a farm category, is active and has a public uuid', function () {
    $category = HealthCategory::create(['farm_type_category_id' => $this->crop->id, 'name' => 'Wilting or drying', 'sort_order' => 1]);

    expect($category->is_active)->toBeTrue()
        ->and($category->uuid)->not->toBeEmpty()
        ->and($category->getRouteKeyName())->toBe('uuid')
        ->and($category->farmTypeCategory->is($this->crop))->toBeTrue();
});

test('a name is unique within its farm category but can repeat in another', function () {
    $livestock = FarmTypeCategory::create(['name' => 'Livestock']);
    HealthCategory::create(['farm_type_category_id' => $this->crop->id, 'name' => 'Not sure', 'sort_order' => 1]);
    HealthCategory::create(['farm_type_category_id' => $livestock->id, 'name' => 'Not sure', 'sort_order' => 1]);

    expect(fn() => HealthCategory::create(['farm_type_category_id' => $this->crop->id, 'name' => 'Not sure', 'sort_order' => 2]))
        ->toThrow(QueryException::class);
});

test('the active list for a farm category is in sort order and leaves out deactivated ones', function () {
    HealthCategory::create(['farm_type_category_id' => $this->crop->id, 'name' => 'B', 'sort_order' => 2]);
    HealthCategory::create(['farm_type_category_id' => $this->crop->id, 'name' => 'A', 'sort_order' => 1]);
    HealthCategory::create(['farm_type_category_id' => $this->crop->id, 'name' => 'Off', 'sort_order' => 3, 'is_active' => false]);

    expect(HealthCategory::activeFor($this->crop)->pluck('name')->all())->toBe(['A', 'B']);
});

test('the seeder fills Crop, Livestock and Aquatic and can run twice without duplicates', function () {
    $this->seed(\Database\Seeders\FarmTypeSeeder::class);

    $this->seed(HealthCategorySeeder::class);
    $this->seed(HealthCategorySeeder::class);

    $count = fn(string $name) => HealthCategory::activeFor(FarmTypeCategory::where('name', $name)->first())->count();

    expect($count('Crop'))->toBe(9)->and($count('Livestock'))->toBe(9)->and($count('Aquatic'))->toBe(7)
        ->and(HealthCategory::count())->toBe(25);
});

test('the seeder does not undo a name or order an admin has changed later', function () {
    $this->seed(\Database\Seeders\FarmTypeSeeder::class);
    $this->seed(HealthCategorySeeder::class);

    $first = HealthCategory::orderBy('id')->first();
    $first->update(['is_active' => false]);

    $this->seed(HealthCategorySeeder::class);

    expect($first->fresh()->is_active)->toBeFalse();
});
