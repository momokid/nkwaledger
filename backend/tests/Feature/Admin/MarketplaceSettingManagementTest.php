<?php

use App\Models\District;
use App\Models\Kiosk;
use App\Models\Region;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\MarketplaceSettingSeeder;
use Database\Seeders\PermissionsSeeder;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(MarketplaceSettingSeeder::class);
});

test('a guest is redirected to login when visiting marketplace settings', function () {
    $this->get('/admin/marketplace/settings')->assertRedirect('/login');
});

test('a user without marketplace-settings.view cannot view settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/marketplace/settings')->assertForbidden();
});

test('a user with marketplace-settings.view can view settings and sees every default key', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('marketplace-settings.view');

    $this->actingAs($user)->get('/admin/marketplace/settings')
        ->assertOk()
        ->assertInertia(fn($page) => $page->has('settings', 10));
});

test('a user without marketplace-settings.update cannot change a setting', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('marketplace-settings.view');

    $this->actingAs($user)->put('/admin/marketplace/settings/marketplace.kiosks_per_email_cap', [
        'value' => '2',
    ])->assertForbidden();
});

// end-to-end proof that a setting change alters behaviour with no code change: raising the cap
// through the real HTTP endpoint lets a supplier's second kiosk register without needing approval
test('raising the kiosk cap through the settings page lets a second kiosk skip admin approval', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['marketplace-settings.view', 'marketplace-settings.update']);

    $this->actingAs($admin)->put('/admin/marketplace/settings/marketplace.kiosks_per_email_cap', [
        'value' => '2',
    ])->assertSessionHasNoErrors();

    $supplierUser = User::factory()->create();
    $supplierUser->assignRole('supplier');
    $supplier = Supplier::factory()->verified()->create(['user_id' => $supplierUser->id]);
    Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

    $this->actingAs($supplierUser)->post('/supplier/kiosks', [
        'name' => 'Second Kiosk',
        'region_id' => Region::factory()->create()->id,
        'district_id' => District::factory()->create()->id,
        'contact_phone' => '0244002233',
    ])->assertSessionHasNoErrors();

    $second = Kiosk::where('supplier_id', $supplier->id)->latest('id')->first();
    expect($second->requires_admin_approval)->toBeFalse();
});
