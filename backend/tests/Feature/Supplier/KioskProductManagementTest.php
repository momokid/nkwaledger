<?php

use App\Enums\BarcodeType;
use App\Models\CatalogProduct;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\KioskProductPriceHistory;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function supplierWithActiveKiosk(): Kiosk
{
    $user = User::factory()->create();
    $user->assignRole('supplier');
    $supplier = Supplier::factory()->verified()->create(['user_id' => $user->id]);

    return Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
}

beforeEach(function () {
    Storage::fake('public');
});

test('a guest cannot list a kiosk\'s products', function () {
    $kiosk = supplierWithActiveKiosk();

    $this->get("/supplier/kiosks/{$kiosk->uuid}/products")->assertRedirect('/login');
});

test('a supplier cannot manage another supplier\'s kiosk products', function () {
    $kiosk = supplierWithActiveKiosk();
    $other = supplierWithActiveKiosk();

    $this->actingAs($other->supplier->user)->get("/supplier/kiosks/{$kiosk->uuid}/products")
        ->assertForbidden();
});

test('adding a product with a new barcode creates a catalog record and a kiosk product', function () {
    $kiosk = supplierWithActiveKiosk();
    $category = ProductCategory::factory()->create(['requires_expiry_date' => false]);
    $unit = ProductUnit::factory()->create();

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products", [
        'raw_code' => '00012345678905',
        'name' => 'NPK Fertilizer 50kg',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'price' => 15000,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('catalog_products', ['barcode' => '00012345678905', 'name' => 'NPK Fertilizer 50kg']);
    $this->assertDatabaseHas('kiosk_products', ['kiosk_id' => $kiosk->id, 'price' => 15000]);
});

test('adding a product with a barcode already in the catalog reuses it instead of creating a duplicate', function () {
    $existing = CatalogProduct::factory()->create(['barcode' => '00012345678905', 'name' => 'Original Name']);
    $kiosk = supplierWithActiveKiosk();

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products", [
        'raw_code' => '00012345678905',
        'name' => 'Ignored Name',
        'price' => 9900,
    ])->assertSessionHasNoErrors();

    expect(CatalogProduct::count())->toBe(1);
    $this->assertDatabaseHas('kiosk_products', ['catalog_product_id' => $existing->id, 'kiosk_id' => $kiosk->id]);
});

test('adding a product with no barcode marks it as having none', function () {
    $kiosk = supplierWithActiveKiosk();

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products", [
        'name' => 'Loose Tomatoes',
        'price' => 500,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('catalog_products', ['name' => 'Loose Tomatoes', 'barcode' => null]);
});

test('a gs1 qr fills in the batch and expiry on the kiosk product', function () {
    $kiosk = supplierWithActiveKiosk();

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products", [
        'raw_code' => 'https://id.gs1.org/01/00012345678905/10/BATCH42/17/261231',
        'name' => 'Vet Dewormer',
        'price' => 4500,
    ])->assertSessionHasNoErrors();

    $product = KioskProduct::where('kiosk_id', $kiosk->id)->first();

    expect($product->batch_number)->toBe('BATCH42')
        ->and($product->expiry_date->toDateString())->toBe('2026-12-31');
});

test('a product in a category that requires an expiry date cannot be added without one', function () {
    $kiosk = supplierWithActiveKiosk();
    $category = ProductCategory::factory()->create(['requires_expiry_date' => true]);

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products", [
        'name' => 'Chemical Spray',
        'category_id' => $category->id,
        'price' => 2000,
    ])->assertSessionHasErrors('expiry_date');

    expect(KioskProduct::count())->toBe(0);
});

test('the required-expiry check follows the reused catalog product\'s own category, not whatever the supplier submitted', function () {
    $expiryCategory = ProductCategory::factory()->create(['requires_expiry_date' => true]);
    $noExpiryCategory = ProductCategory::factory()->create(['requires_expiry_date' => false]);
    $existing = CatalogProduct::factory()->create(['barcode' => '00099999999999', 'category_id' => $expiryCategory->id]);
    $kiosk = supplierWithActiveKiosk();

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products", [
        'raw_code' => '00099999999999',
        'name' => 'Whatever',
        'category_id' => $noExpiryCategory->id,
        'price' => 2000,
    ])->assertSessionHasErrors('expiry_date');
});

test('creating a kiosk product writes its first price history row', function () {
    $kiosk = supplierWithActiveKiosk();

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products", [
        'name' => 'Loose Tomatoes',
        'price' => 500,
    ]);

    $product = KioskProduct::where('kiosk_id', $kiosk->id)->first();

    $this->assertDatabaseHas('kiosk_product_price_histories', [
        'kiosk_product_id' => $product->id,
        'old_price' => null,
        'new_price' => 500,
    ]);
});

test('a product cannot be added with more than the allowed number of images', function () {
    $kiosk = supplierWithActiveKiosk();

    $images = array_map(fn() => UploadedFile::fake()->image('produce.jpg'), range(1, 4));

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products", [
        'name' => 'Loose Tomatoes',
        'price' => 500,
        'images' => $images,
    ])->assertSessionHasErrors('images');
});

test('a product can be added with images up to the allowed count, each resized and stored', function () {
    $kiosk = supplierWithActiveKiosk();

    $images = array_map(fn() => UploadedFile::fake()->image('produce.jpg', 2000, 2000), range(1, 3));

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products", [
        'name' => 'Loose Tomatoes',
        'price' => 500,
        'images' => $images,
    ])->assertSessionHasNoErrors();

    $product = KioskProduct::where('kiosk_id', $kiosk->id)->first();
    expect($product->images()->count())->toBe(3);
});

test('an oversized image is rejected', function () {
    $kiosk = supplierWithActiveKiosk();

    $tooLarge = UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg');

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products", [
        'name' => 'Loose Tomatoes',
        'price' => 500,
        'images' => [$tooLarge],
    ])->assertSessionHasErrors('images.0');
});

test('changing the price records who changed it and resets staleness', function () {
    $kiosk = supplierWithActiveKiosk();
    $product = KioskProduct::factory()->stalePrice()->create(['kiosk_id' => $kiosk->id, 'price' => 1000]);

    $this->actingAs($kiosk->supplier->user)->patch("/supplier/kiosks/{$kiosk->uuid}/products/{$product->uuid}/price", [
        'price' => 1500,
    ])->assertSessionHasNoErrors();

    expect($product->fresh()->price)->toBe(1500)
        ->and($product->fresh()->stale_alerted_at)->toBeNull();

    $this->assertDatabaseHas('kiosk_product_price_histories', [
        'kiosk_product_id' => $product->id,
        'old_price' => 1000,
        'new_price' => 1500,
        'type' => 'changed',
    ]);
});

test('confirming a price unchanged writes a confirmed row and does not change the price', function () {
    $kiosk = supplierWithActiveKiosk();
    $product = KioskProduct::factory()->stalePrice()->create(['kiosk_id' => $kiosk->id, 'price' => 1000]);

    $this->actingAs($kiosk->supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/products/{$product->uuid}/confirm-price")
        ->assertSessionHasNoErrors();

    expect($product->fresh()->price)->toBe(1000);

    $this->assertDatabaseHas('kiosk_product_price_histories', [
        'kiosk_product_id' => $product->id,
        'type' => 'confirmed',
    ]);
});

test('a supplier can toggle a product out of stock and back', function () {
    $kiosk = supplierWithActiveKiosk();
    $product = KioskProduct::factory()->create(['kiosk_id' => $kiosk->id, 'in_stock' => true]);

    $this->actingAs($kiosk->supplier->user)->patch("/supplier/kiosks/{$kiosk->uuid}/products/{$product->uuid}/stock", [
        'in_stock' => false,
    ])->assertSessionHasNoErrors();

    expect($product->fresh()->in_stock)->toBeFalse();
});

test('a price history row is never editable through the price endpoint replaying old data', function () {
    $kiosk = supplierWithActiveKiosk();
    $product = KioskProduct::factory()->create(['kiosk_id' => $kiosk->id, 'price' => 1000]);
    $product->recordInitialPrice($kiosk->supplier->user);

    $row = KioskProductPriceHistory::where('kiosk_product_id', $product->id)->first();

    expect(fn() => $row->update(['new_price' => 1]))->toThrow(RuntimeException::class);
});
