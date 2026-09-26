<?php

use App\Enums\KioskProductStatus;
use App\Models\CatalogProduct;
use App\Models\Community;
use App\Models\District;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\Order;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Region;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

test('a guest is redirected to login', function () {
    $this->get('/my-marketplace')->assertRedirect('/login');
});

test('a farmer with no profile is forbidden', function () {
    $bare = User::factory()->create();
    $bare->assignRole('farmer');

    $this->actingAs($bare)->get('/my-marketplace')->assertForbidden();
});

test('a user without the marketplace-browse permission is forbidden', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/my-marketplace')->assertForbidden();
});

test('a farmer with no matching products sees an empty page, not an error', function () {
    $this->actingAs($this->farmerUser)->get('/my-marketplace')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('MyMarketplace/Index')
            ->where('products.data', []));
});

function makeKioskWithProduct(FarmerProfile $farmer, array $productOverrides = []): array
{
    $district = District::factory()->create();
    $farmer->update(['community_id' => Community::factory()->create(['district_id' => $district->id])->id]);

    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $user->id]);
    $kiosk = Kiosk::factory()->confirmed()->create([
        'supplier_id' => $supplier->id,
        'district_id' => $district->id,
        'region_id' => $district->region_id,
        'contact_phone' => '0241234567',
    ]);

    $catalogProduct = CatalogProduct::factory()->create();
    $product = KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $kiosk->id,
        'catalog_product_id' => $catalogProduct->id,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
        ...$productOverrides,
    ]);

    return [$kiosk, $product];
}

test('the product grid shows the product\'s real photo, price, and kiosk', function () {
    Storage::fake('public');

    [$kiosk, $product] = makeKioskWithProduct($this->profile, ['price' => 3200]);
    $product->images()->create(['path' => 'kiosk-products/sample.jpg']);

    $this->actingAs($this->farmerUser)->get('/my-marketplace')
        ->assertInertia(fn($page) => $page
            ->where('products.data.0.kiosk_product_id', $product->id)
            ->where('products.data.0.kiosk_uuid', $kiosk->uuid)
            ->where('products.data.0.kiosk_name', $kiosk->name)
            ->where('products.data.0.distance_label', 'Your district')
            ->where('products.data.0.price_minor', 3200)
            ->where('products.data.0.image_url', fn($url) => str_contains($url, 'kiosk-products/sample.jpg')));
});

test('a product with no photo shows a null image_url, never a fabricated one', function () {
    [, $product] = makeKioskWithProduct($this->profile);

    $this->actingAs($this->farmerUser)->get('/my-marketplace')
        ->assertInertia(fn($page) => $page->where('products.data.0.image_url', null));
});

test('a kiosk contributing several products contributes one grid row per product', function () {
    [$kiosk] = makeKioskWithProduct($this->profile);
    KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $kiosk->id,
        'catalog_product_id' => CatalogProduct::factory()->create()->id,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-marketplace')
        ->assertInertia(fn($page) => $page->where('products.data', fn($rows) => count($rows) === 2));
});

test('the category query param narrows the product grid', function () {
    $matchingCategory = ProductCategory::factory()->create();
    $otherCategory = ProductCategory::factory()->create();

    [, $matchingProduct] = makeKioskWithProduct($this->profile);
    $matchingProduct->catalogProduct()->update(['category_id' => $matchingCategory->id]);

    $district = District::where('id', $this->profile->fresh()->community->district_id)->first();
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $user->id]);
    $otherKiosk = Kiosk::factory()->confirmed()->create([
        'supplier_id' => $supplier->id,
        'district_id' => $district->id,
        'region_id' => $district->region_id,
    ]);
    KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $otherKiosk->id,
        'catalog_product_id' => CatalogProduct::factory()->create(['category_id' => $otherCategory->id])->id,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
    ]);

    $this->actingAs($this->farmerUser)->get("/my-marketplace?category={$matchingCategory->id}")
        ->assertInertia(fn($page) => $page
            ->where('products.data.0.kiosk_product_id', $matchingProduct->id)
            ->where('products.data', fn($rows) => count($rows) === 1));
});

test('the kiosk-detail page shows only that kiosk\'s own products', function () {
    [$kiosk, $product] = makeKioskWithProduct($this->profile);

    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $user->id]);
    $otherKiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
    KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $otherKiosk->id,
        'catalog_product_id' => CatalogProduct::factory()->create()->id,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
    ]);

    $this->actingAs($this->farmerUser)->get("/my-marketplace/kiosks/{$kiosk->uuid}")
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('MyMarketplace/Kiosk')
            ->where('kiosk.uuid', $kiosk->uuid)
            ->where('products', fn($rows) => count($rows) === 1 && $rows[0]['kiosk_product_id'] === $product->id));
});

test('ordering a specific product from the grid creates a correct order and order item', function () {
    [$kiosk, $product] = makeKioskWithProduct($this->profile, ['price' => 2000]);
    $farmUnit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);

    $this->actingAs($this->farmerUser)->post("/my-marketplace/kiosks/{$kiosk->uuid}/orders", [
        'items' => [['kiosk_product_id' => $product->id, 'quantity' => 3]],
        'payment_method' => 'cod',
        'farm_unit_id' => $farmUnit->id,
    ])->assertSessionHasNoErrors();

    $order = Order::where('kiosk_id', $kiosk->id)->first();

    expect($order)->not->toBeNull()
        ->and($order->amount_minor)->toBe(6000)
        ->and($order->items()->first()->kiosk_product_id)->toBe($product->id)
        ->and($order->items()->first()->quantity)->toBe(3);
});
