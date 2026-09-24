<?php

use App\Models\CatalogProduct;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
});

test('a guest is redirected to login when visiting the catalog', function () {
    $this->get('/admin/marketplace/catalog')->assertRedirect('/login');
});

test('a user without marketplace-catalog.view cannot view the catalog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/marketplace/catalog')->assertForbidden();
});

test('a user with marketplace-catalog.view can view the catalog', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('marketplace-catalog.view');

    $this->actingAs($user)->get('/admin/marketplace/catalog')->assertOk();
});

test('an admin can seed a catalog product', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['marketplace-catalog.view', 'marketplace-catalog.create']);
    $category = ProductCategory::factory()->create();
    $unit = ProductUnit::factory()->create();

    $this->actingAs($admin)->post('/admin/marketplace/catalog', [
        'name' => 'NPK Fertilizer 50kg',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('catalog_products', [
        'name' => 'NPK Fertilizer 50kg',
        'seeded' => true,
        'created_by' => $admin->id,
    ]);
});

test('seeding a catalog product needs the create permission', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-catalog.view');

    $this->actingAs($admin)->post('/admin/marketplace/catalog', ['name' => 'Whatever'])->assertForbidden();
});

test('an admin can merge a duplicate catalog product into another', function () {
    $keeper = CatalogProduct::factory()->create();
    $duplicate = CatalogProduct::factory()->create();

    $admin = User::factory()->create();
    $admin->givePermissionTo(['marketplace-catalog.view', 'marketplace-catalog.merge']);

    $this->actingAs($admin)->patch("/admin/marketplace/catalog/{$duplicate->uuid}/merge", [
        'into' => $keeper->uuid,
    ])->assertSessionHasNoErrors();

    expect($duplicate->fresh()->merged_into_id)->toBe($keeper->id);
});

test('merging needs the merge permission', function () {
    $keeper = CatalogProduct::factory()->create();
    $duplicate = CatalogProduct::factory()->create();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('marketplace-catalog.view');

    $this->actingAs($viewer)->patch("/admin/marketplace/catalog/{$duplicate->uuid}/merge", [
        'into' => $keeper->uuid,
    ])->assertForbidden();
});

test('an admin can inspect a supplier\'s stock', function () {
    $supplier = Supplier::factory()->verified()->create();
    $kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
    KioskProduct::factory()->create(['kiosk_id' => $kiosk->id]);

    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-catalog.view');

    $this->actingAs($admin)->get("/admin/marketplace/suppliers/{$supplier->uuid}/stock")
        ->assertOk()
        ->assertInertia(fn($page) => $page->has('products', 1));
});

test('inspecting stock needs the catalog view permission', function () {
    $supplier = Supplier::factory()->verified()->create();

    $user = User::factory()->create();

    $this->actingAs($user)->get("/admin/marketplace/suppliers/{$supplier->uuid}/stock")->assertForbidden();
});
