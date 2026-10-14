<?php

use App\Models\CatalogProduct;
use App\Models\Community;
use App\Models\District;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Enums\KioskProductStatus;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

// the browse index itself moved to Market Center (Step 8) - see MarketCenterTest.php
// for the product-grid/photo/ranking/category coverage that used to live here. What
// remains here is what did NOT move: the redirect, and the kiosk-detail/order flow,
// which are unchanged pages behind unchanged routes
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

test('the old browse destination redirects to Market Center', function () {
    $this->actingAs($this->farmerUser)->get('/my-marketplace')
        ->assertRedirect('/market-center');
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

test('a user without marketplace-browse.view cannot view a kiosk\'s storefront', function () {
    $bare = User::factory()->create();
    [$kiosk] = makeKioskWithProduct($this->profile);

    $this->actingAs($bare)->get("/my-marketplace/kiosks/{$kiosk->uuid}")->assertForbidden();
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
