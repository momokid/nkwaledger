<?php

use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\KioskProductImage;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    Storage::fake('public');
});

test('an admin with catalog view can remove a product image', function () {
    $supplier = Supplier::factory()->verified()->create();
    $kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
    $product = KioskProduct::factory()->create(['kiosk_id' => $kiosk->id]);
    Storage::disk('public')->put('kiosk-products/photo.webp', 'fake');
    $image = KioskProductImage::create(['kiosk_product_id' => $product->id, 'path' => 'kiosk-products/photo.webp']);

    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-catalog.view');

    $this->actingAs($admin)->delete("/admin/marketplace/kiosk-products/{$product->uuid}/images/{$image->id}")
        ->assertSessionHasNoErrors();

    expect(KioskProductImage::find($image->id))->toBeNull();
    Storage::disk('public')->assertMissing('kiosk-products/photo.webp');
});

test('removing an image needs the catalog view permission', function () {
    $supplier = Supplier::factory()->verified()->create();
    $kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
    $product = KioskProduct::factory()->create(['kiosk_id' => $kiosk->id]);
    $image = KioskProductImage::create(['kiosk_product_id' => $product->id, 'path' => 'kiosk-products/photo.webp']);

    $user = User::factory()->create();

    $this->actingAs($user)->delete("/admin/marketplace/kiosk-products/{$product->uuid}/images/{$image->id}")
        ->assertForbidden();
});
