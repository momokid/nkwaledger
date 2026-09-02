<?php

use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use Database\Seeders\FarmTypeSeeder;

it('seeds the three categories', function () {
    $this->seed(FarmTypeSeeder::class);

    expect(FarmTypeCategory::count())->toBe(3);
    foreach (['Crop', 'Livestock', 'Aquatic'] as $name) {
        $this->assertDatabaseHas('farm_type_categories', ['name' => $name]);
    }
});

it('seeds twenty two farm types', function () {
    $this->seed(FarmTypeSeeder::class);

    expect(FarmType::count())->toBe(22);
});

it('seeds the crop types under the Crop category', function () {
    $this->seed(FarmTypeSeeder::class);

    $category = FarmTypeCategory::where('name', 'Crop')->first();

    foreach (
        [
            'Maize',
            'Rice',
            'Cassava',
            'Yam',
            'Plantain',
            'Cocoa',
            'Oil Palm',
            'Cashew',
            'Groundnut',
            'Cowpea',
            'Soya Bean',
            'Tomato',
            'Pepper',
            'Onion',
        ] as $name
    ) {
        $this->assertDatabaseHas('farm_types', [
            'name' => $name,
            'category_id' => $category->id,
        ]);
    }
});

it('seeds the livestock types under the Livestock category', function () {
    $this->seed(FarmTypeSeeder::class);

    $category = FarmTypeCategory::where('name', 'Livestock')->first();

    foreach (['Cattle', 'Sheep', 'Goat', 'Pig', 'Layers', 'Broilers'] as $name) {
        $this->assertDatabaseHas('farm_types', [
            'name' => $name,
            'category_id' => $category->id,
        ]);
    }
});

it('seeds the aquatic types under the Aquatic category', function () {
    $this->seed(FarmTypeSeeder::class);

    $category = FarmTypeCategory::where('name', 'Aquatic')->first();

    foreach (['Tilapia', 'Catfish'] as $name) {
        $this->assertDatabaseHas('farm_types', [
            'name' => $name,
            'category_id' => $category->id,
        ]);
    }
});

it('does not duplicate categories or farm types when run twice', function () {
    $this->seed(FarmTypeSeeder::class);
    $this->seed(FarmTypeSeeder::class);

    expect(FarmTypeCategory::count())->toBe(3);
    expect(FarmType::count())->toBe(22);
});
