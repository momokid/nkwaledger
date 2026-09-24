<?php

use App\Enums\KioskStatus;
use App\Enums\SupplierAccountStatus;
use App\Models\Kiosk;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
});

test('a guest is redirected to login when visiting the marketplace suppliers list', function () {
    $this->get('/admin/marketplace/suppliers')->assertRedirect('/login');
});

test('a user without marketplace-suppliers.view cannot view the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/marketplace/suppliers')->assertForbidden();
});

test('a user with marketplace-suppliers.view can view the list', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('marketplace-suppliers.view');

    $this->actingAs($user)->get('/admin/marketplace/suppliers')->assertOk();
});

// a partial eager-load that forgets phone_verified_at would silently read every
// verified supplier back as unverified - this is the column list that must stay complete
test('a verified supplier shows as verified on the admin list, not unverified', function () {
    $supplier = Supplier::factory()->verified()->create();

    $user = User::factory()->create();
    $user->givePermissionTo('marketplace-suppliers.view');

    $this->actingAs($user)->get('/admin/marketplace/suppliers')
        ->assertInertia(fn($page) => $page
            ->where('suppliers.data.0.verification_status', 'email_and_phone_verified'));
});

test('suspending a supplier suspends every one of that supplier\'s kiosks in one action', function () {
    $supplier = Supplier::factory()->verified()->create();
    $first = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
    $second = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-suppliers.suspend');

    $this->actingAs($admin)->patch("/admin/marketplace/suppliers/{$supplier->uuid}/suspend")
        ->assertSessionHasNoErrors();

    expect($supplier->fresh()->isSuspended())->toBeTrue()
        ->and($first->fresh()->status)->toBe(KioskStatus::Suspended)
        ->and($second->fresh()->status)->toBe(KioskStatus::Suspended);
});

test('restoring a supplier does not automatically restore its kiosks', function () {
    $supplier = Supplier::factory()->verified()->suspended()->create();
    $kiosk = Kiosk::factory()->create(['supplier_id' => $supplier->id, 'status' => KioskStatus::Suspended]);

    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-suppliers.suspend');

    $this->actingAs($admin)->patch("/admin/marketplace/suppliers/{$supplier->uuid}/restore")
        ->assertSessionHasNoErrors();

    expect($supplier->fresh()->account_status)->toBe(SupplierAccountStatus::Active)
        ->and($kiosk->fresh()->status)->toBe(KioskStatus::Suspended);
});

test('suspending a supplier needs the suspend permission', function () {
    $supplier = Supplier::factory()->verified()->create();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('marketplace-suppliers.view');

    $this->actingAs($viewer)->patch("/admin/marketplace/suppliers/{$supplier->uuid}/suspend")->assertForbidden();
});
