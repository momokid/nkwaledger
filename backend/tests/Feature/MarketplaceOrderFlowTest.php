<?php

use App\Enums\KioskProductStatus;
use App\Models\AccountingPeriod;
use App\Models\CatalogProduct;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RegionDistrictSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(RegionDistrictSeeder::class);

    AccountingPeriod::create([
        'name' => 'Current',
        'starts_on' => now()->startOfMonth(),
        'ends_on' => now()->endOfMonth(),
    ]);

    $this->supplierUser = User::factory()->create();
    $this->supplierUser->assignRole('supplier');
    $this->supplier = Supplier::factory()->create(['user_id' => $this->supplierUser->id]);
    $this->kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $this->supplier->id]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->farmer = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
    $this->farmUnit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->farmer->id]);

    $catalogProduct = CatalogProduct::factory()->create();
    $this->kioskProduct = KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $this->kiosk->id,
        'catalog_product_id' => $catalogProduct->id,
        'price' => 1500,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
    ]);
});

test('a farmer can submit a cart to a kiosk', function () {
    $this->actingAs($this->farmerUser)->post("/my-marketplace/kiosks/{$this->kiosk->uuid}/orders", [
        'items' => [['kiosk_product_id' => $this->kioskProduct->id, 'quantity' => 2]],
        'payment_method' => 'bank',
        'farm_unit_id' => $this->farmUnit->id,
    ])->assertSessionHasNoErrors();

    expect(Order::where('kiosk_id', $this->kiosk->id)->where('farmer_profile_id', $this->farmer->id)->exists())->toBeTrue();
});

test('a farmer cannot submit a cart choosing another farmer\'s farm unit', function () {
    $otherFarmer = FarmerProfile::factory()->create();
    $otherUnit = FarmUnit::factory()->create(['farmer_profile_id' => $otherFarmer->id]);

    $this->actingAs($this->farmerUser)->post("/my-marketplace/kiosks/{$this->kiosk->uuid}/orders", [
        'items' => [['kiosk_product_id' => $this->kioskProduct->id, 'quantity' => 1]],
        'payment_method' => 'bank',
        'farm_unit_id' => $otherUnit->id,
    ])->assertSessionHasErrors('farm_unit_id');
});

test('a guest cannot submit a cart', function () {
    $this->post("/my-marketplace/kiosks/{$this->kiosk->uuid}/orders", [])->assertRedirect('/login');
});

test('a supplier sees incoming orders on their kiosk and can confirm one', function () {
    $order = Order::factory()->create([
        'kiosk_id' => $this->kiosk->id,
        'farmer_profile_id' => $this->farmer->id,
        'farm_unit_id' => $this->farmUnit->id,
    ]);

    $this->actingAs($this->supplierUser)->get('/supplier/orders')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Supplier/Orders/Index')
            ->where('orders.0.uuid', $order->uuid));

    $this->actingAs($this->supplierUser)->post("/supplier/orders/{$order->uuid}/confirm")
        ->assertSessionHasNoErrors();

    expect($order->fresh()->confirmed_at)->not->toBeNull();
});

test('a supplier cannot confirm another supplier\'s order', function () {
    $order = Order::factory()->create([
        'kiosk_id' => $this->kiosk->id,
        'farmer_profile_id' => $this->farmer->id,
        'farm_unit_id' => $this->farmUnit->id,
    ]);

    $otherSupplierUser = User::factory()->create();
    $otherSupplierUser->assignRole('supplier');
    Supplier::factory()->create(['user_id' => $otherSupplierUser->id]);

    $this->actingAs($otherSupplierUser)->post("/supplier/orders/{$order->uuid}/confirm")->assertForbidden();

    expect($order->fresh()->confirmed_at)->toBeNull();
});

test('a farmer can mark an order received and then review it once confirmed', function () {
    $order = Order::factory()->confirmed()->create([
        'kiosk_id' => $this->kiosk->id,
        'farmer_profile_id' => $this->farmer->id,
        'farm_unit_id' => $this->farmUnit->id,
    ]);

    $this->actingAs($this->farmerUser)->post("/my-marketplace/orders/{$order->uuid}/receive")
        ->assertSessionHasNoErrors();

    expect($order->fresh()->received_at)->not->toBeNull();

    $this->actingAs($this->farmerUser)->post("/my-marketplace/orders/{$order->uuid}/review", [
        'rating' => 5,
        'comment' => 'Fast and friendly.',
    ])->assertSessionHasNoErrors();

    expect($order->fresh()->review)->not->toBeNull();
});
