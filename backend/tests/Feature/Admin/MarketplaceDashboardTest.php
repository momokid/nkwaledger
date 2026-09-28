<?php

use App\Enums\KioskProductStatus;
use App\Models\CatalogProduct;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\Order;
use App\Models\ProduceListing;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('a guest cannot view the marketplace dashboard', function () {
    $this->get('/admin/marketplace/dashboard')->assertRedirect('/login');
});

test('the dashboard shows real counts, not placeholders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $supplierUser = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);
    $kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
    KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $kiosk->id,
        'catalog_product_id' => CatalogProduct::factory()->create()->id,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
    ]);

    $farmer = FarmerProfile::factory()->create();
    $farmType = FarmType::where('name', 'Maize')->firstOrFail();
    $unit = FarmUnit::factory()->approved()->create(['farm_type_id' => $farmType->id, 'farmer_profile_id' => $farmer->id]);
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 10, 'current_quantity' => 10]);
    ProduceListing::factory()->create(['farm_unit_stock_id' => $stock->id, 'farmer_profile_id' => $farmer->id]);

    Order::factory()->create(['kiosk_id' => $kiosk->id, 'farmer_profile_id' => $farmer->id, 'requested_at' => now()]);

    $this->actingAs($admin)->get('/admin/marketplace/dashboard')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Admin/Marketplace/Dashboard/Index')
            ->where('stats.active_kiosk_products', 1)
            ->where('stats.active_produce_listings', 1)
            ->where('stats.orders_last_7_days', 1));
});
