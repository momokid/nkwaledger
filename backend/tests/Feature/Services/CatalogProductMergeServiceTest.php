<?php

use App\Models\CatalogProduct;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Services\CatalogProductMergeService;

beforeEach(function () {
    $this->merger = app(CatalogProductMergeService::class);
});

test('merging points the duplicate at the keeper and soft-deletes the duplicate', function () {
    $keeper = CatalogProduct::factory()->create();
    $duplicate = CatalogProduct::factory()->create();

    $this->merger->merge($duplicate, $keeper);

    expect($duplicate->fresh()->merged_into_id)->toBe($keeper->id);
    $this->assertSoftDeleted('catalog_products', ['id' => $duplicate->id]);
});

test('a kiosk product on the duplicate is reassigned to the keeper', function () {
    $keeper = CatalogProduct::factory()->create();
    $duplicate = CatalogProduct::factory()->create();
    $kioskProduct = KioskProduct::factory()->create(['catalog_product_id' => $duplicate->id]);

    $this->merger->merge($duplicate, $keeper);

    expect($kioskProduct->fresh()->catalog_product_id)->toBe($keeper->id);
});

test('a kiosk that already listed the keeper keeps that row and drops the duplicate\'s, rather than violating the one-per-kiosk rule', function () {
    $keeper = CatalogProduct::factory()->create();
    $duplicate = CatalogProduct::factory()->create();
    $kiosk = Kiosk::factory()->create();

    $keeperListing = KioskProduct::factory()->create(['kiosk_id' => $kiosk->id, 'catalog_product_id' => $keeper->id]);
    $duplicateListing = KioskProduct::factory()->create(['kiosk_id' => $kiosk->id, 'catalog_product_id' => $duplicate->id]);

    $this->merger->merge($duplicate, $keeper);

    expect($keeperListing->fresh()->catalog_product_id)->toBe($keeper->id);
    $this->assertSoftDeleted('kiosk_products', ['id' => $duplicateListing->id]);
});

test('merging a product into itself is refused', function () {
    $product = CatalogProduct::factory()->create();

    expect(fn() => $this->merger->merge($product, $product))->toThrow(InvalidArgumentException::class);
});
