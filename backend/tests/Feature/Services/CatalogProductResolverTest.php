<?php

use App\Enums\BarcodeType;
use App\Models\CatalogProduct;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\CatalogProductResolver;

beforeEach(function () {
    $this->resolver = app(CatalogProductResolver::class);
    $this->category = ProductCategory::factory()->create();
    $this->unit = ProductUnit::factory()->create();
});

test('a new barcode creates a catalog product owned by the supplier who typed it', function () {
    $supplier = User::factory()->create();

    $result = $this->resolver->resolve([
        'raw_code' => '00012345678905',
        'name' => 'NPK Fertilizer 50kg',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ], $supplier);

    expect($result->catalogProduct->barcode)->toBe('00012345678905')
        ->and($result->catalogProduct->barcode_type)->toBe(BarcodeType::Barcode)
        ->and($result->catalogProduct->name)->toBe('NPK Fertilizer 50kg')
        ->and($result->catalogProduct->created_by)->toBe($supplier->id)
        ->and($result->batch)->toBeNull()
        ->and($result->expiry)->toBeNull();
});

test('a second supplier typing the same barcode reuses the existing catalog record, ignoring their own typed name', function () {
    $existing = CatalogProduct::factory()->create([
        'barcode' => '00012345678905',
        'barcode_type' => BarcodeType::Barcode,
        'name' => 'Original Name',
    ]);

    $secondSupplier = User::factory()->create();

    $result = $this->resolver->resolve([
        'raw_code' => '00012345678905',
        'name' => 'A Totally Different Name',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ], $secondSupplier);

    expect($result->catalogProduct->id)->toBe($existing->id)
        ->and($result->catalogProduct->name)->toBe('Original Name')
        ->and(CatalogProduct::count())->toBe(1);
});

test('no code at all creates a product with no barcode', function () {
    $supplier = User::factory()->create();

    $result = $this->resolver->resolve([
        'raw_code' => null,
        'name' => 'Loose Maize Seed',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ], $supplier);

    expect($result->catalogProduct->hasBarcode())->toBeFalse()
        ->and($result->catalogProduct->barcode_type)->toBeNull();
});

test('a gs1 digital link qr creates a product keyed by the parsed gtin, not the whole url', function () {
    $supplier = User::factory()->create();

    $result = $this->resolver->resolve([
        'raw_code' => 'https://id.gs1.org/01/00012345678905/10/BATCH42/17/261231',
        'name' => 'Vet Dewormer',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ], $supplier);

    expect($result->catalogProduct->barcode)->toBe('00012345678905')
        ->and($result->catalogProduct->barcode_type)->toBe(BarcodeType::Gs1Qr)
        ->and($result->batch)->toBe('BATCH42')
        ->and($result->expiry->toDateString())->toBe('2026-12-31');
});

test('a gs1 qr for an already-known gtin reuses the catalog record the same as a typed barcode would', function () {
    $existing = CatalogProduct::factory()->create([
        'barcode' => '00012345678905',
        'barcode_type' => BarcodeType::Barcode,
    ]);

    $supplier = User::factory()->create();

    $result = $this->resolver->resolve([
        'raw_code' => 'https://id.gs1.org/01/00012345678905/17/261231',
        'name' => 'Whatever',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ], $supplier);

    expect($result->catalogProduct->id)->toBe($existing->id)
        ->and(CatalogProduct::count())->toBe(1);
});

test('unrecognised qr or typed text is stored as-is and never treated as a link', function () {
    $supplier = User::factory()->create();

    $result = $this->resolver->resolve([
        'raw_code' => 'https://example.com/some/random/page',
        'name' => 'Mystery Product',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ], $supplier);

    expect($result->catalogProduct->barcode)->toBe('https://example.com/some/random/page')
        ->and($result->catalogProduct->barcode_type)->toBe(BarcodeType::Other);
});
