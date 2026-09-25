<?php

use App\Enums\KioskProductStatus;
use App\Models\CatalogProduct;
use App\Models\Community;
use App\Models\District;
use App\Models\FarmerProfile;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\Region;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

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

test('a farmer with no matching kiosks sees an empty page, not an error', function () {
    $this->actingAs($this->farmerUser)->get('/my-marketplace')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('MyMarketplace/Index')
            ->where('kiosks.data', []));
});

test('a farmer sees a kiosk with its distance label, contact phone, and empty review state', function () {
    $district = District::factory()->create();
    $this->profile->update(['community_id' => Community::factory()->create(['district_id' => $district->id])->id]);

    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $user->id]);
    $kiosk = Kiosk::factory()->confirmed()->create([
        'supplier_id' => $supplier->id,
        'district_id' => $district->id,
        'region_id' => $district->region_id,
        'contact_phone' => '0241234567',
    ]);
    $catalogProduct = CatalogProduct::factory()->create();
    KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $kiosk->id,
        'catalog_product_id' => $catalogProduct->id,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
    ]);

    $this->actingAs($this->farmerUser)->get('/my-marketplace')
        ->assertInertia(fn($page) => $page
            ->where('kiosks.data.0.uuid', $kiosk->uuid)
            ->where('kiosks.data.0.contact_phone', '0241234567')
            ->where('kiosks.data.0.distance_label', 'Your district')
            ->where('kiosks.data.0.rating_average', null)
            ->where('kiosks.data.0.review_count', 0)
            ->where('kiosks.data.0.sales_count', 0));
});

test('the category query param narrows the results', function () {
    $district = District::factory()->create();
    $this->profile->update(['community_id' => Community::factory()->create(['district_id' => $district->id])->id]);

    $matchingCategory = \App\Models\ProductCategory::factory()->create();
    $otherCategory = \App\Models\ProductCategory::factory()->create();

    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $user->id]);

    $matchingKiosk = Kiosk::factory()->confirmed()->create([
        'supplier_id' => $supplier->id,
        'district_id' => $district->id,
        'region_id' => $district->region_id,
    ]);
    KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $matchingKiosk->id,
        'catalog_product_id' => CatalogProduct::factory()->create(['category_id' => $matchingCategory->id])->id,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
    ]);

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
            ->where('kiosks.data.0.uuid', $matchingKiosk->uuid)
            ->where('kiosks.data', fn($rows) => count($rows) === 1));
});
