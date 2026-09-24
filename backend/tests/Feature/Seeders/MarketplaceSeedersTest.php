<?php

use App\Models\MarketplaceSetting;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Services\SettingsService;
use Database\Seeders\MarketplaceSettingSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\ProductUnitSeeder;

test('the marketplace setting seeder inserts every default key', function () {
    $this->seed(MarketplaceSettingSeeder::class);

    foreach (SettingsService::DEFAULTS as $key => $value) {
        $this->assertDatabaseHas('marketplace_settings', ['key' => $key, 'value' => $value]);
    }
});

test('running the marketplace setting seeder twice does not duplicate rows or overwrite a changed value', function () {
    $this->seed(MarketplaceSettingSeeder::class);

    MarketplaceSetting::where('key', 'marketplace.kiosks_per_email_cap')->update(['value' => '2']);

    $this->seed(MarketplaceSettingSeeder::class);

    expect(MarketplaceSetting::where('key', 'marketplace.kiosks_per_email_cap')->count())->toBe(1)
        ->and(MarketplaceSetting::where('key', 'marketplace.kiosks_per_email_cap')->value('value'))->toBe('2');
});

test('the product category seeder inserts the categories that must carry an expiry date', function () {
    $this->seed(ProductCategorySeeder::class);

    expect(ProductCategory::where('name', 'Seed')->value('requires_expiry_date'))->toBeTrue()
        ->and(ProductCategory::where('name', 'Chemical')->value('requires_expiry_date'))->toBeTrue()
        ->and(ProductCategory::where('name', 'Veterinary Drugs')->value('requires_expiry_date'))->toBeTrue()
        ->and(ProductCategory::where('name', 'Feed')->value('requires_expiry_date'))->toBeTrue()
        ->and(ProductCategory::where('name', 'Fertilizer')->value('requires_expiry_date'))->toBeFalse();
});

test('running the product category seeder twice does not duplicate rows', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCategorySeeder::class);

    expect(ProductCategory::count())->toBe(6);
});

test('the product unit seeder inserts the spec example units', function () {
    $this->seed(ProductUnitSeeder::class);

    foreach (['Kg', 'Litre', 'Bag', 'Piece'] as $name) {
        $this->assertDatabaseHas('product_units', ['name' => $name]);
    }
});

test('running the product unit seeder twice does not duplicate rows', function () {
    $this->seed(ProductUnitSeeder::class);
    $this->seed(ProductUnitSeeder::class);

    expect(ProductUnit::count())->toBe(4);
});
