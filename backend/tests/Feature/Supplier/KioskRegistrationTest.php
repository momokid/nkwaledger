<?php

use App\Enums\KioskStatus;
use App\Models\District;
use App\Models\Kiosk;
use App\Models\OtpCode;
use App\Models\Region;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
});

function verifiedSupplierUser(): Supplier
{
    $user = User::factory()->create(['password' => bcrypt('Password@123')]);
    $user->assignRole('supplier');

    return Supplier::factory()->verified()->create(['user_id' => $user->id]);
}

function kioskPayload(): array
{
    return [
        'name' => 'Adom Agro Kiosk',
        'region_id' => Region::factory()->create()->id,
        'district_id' => District::factory()->create()->id,
        'contact_phone' => '0244001122',
    ];
}

test('a guest cannot register a kiosk', function () {
    $this->post('/supplier/kiosks', kioskPayload())->assertRedirect('/login');
});

test('a verified supplier can register their first kiosk', function () {
    Mail::fake();
    $supplier = verifiedSupplierUser();

    $this->actingAs($supplier->user)->post('/supplier/kiosks', kioskPayload())
        ->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('kiosks', [
        'supplier_id' => $supplier->id,
        'name' => 'Adom Agro Kiosk',
        'status' => KioskStatus::PendingConfirmation->value,
        'requires_admin_approval' => false,
    ]);
});

test('a kiosk is hidden from the visible scope until confirmed', function () {
    $supplier = verifiedSupplierUser();
    $kiosk = Kiosk::factory()->create(['supplier_id' => $supplier->id]);

    expect(Kiosk::visible()->find($kiosk->id))->toBeNull();
});

test('confirming a kiosk with the right code makes it visible', function () {
    $supplier = verifiedSupplierUser();
    $kiosk = Kiosk::factory()->create(['supplier_id' => $supplier->id]);

    OtpCode::create([
        'identifier' => $supplier->email,
        'code' => Hash::make('112233'),
        'type' => 'kiosk_confirmation',
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/confirm", ['code' => '112233'])
        ->assertSessionHasNoErrors();

    $fresh = $kiosk->fresh();
    expect($fresh->isConfirmed())->toBeTrue()
        ->and($fresh->isVisible())->toBeTrue();
});

test('a wrong confirmation code leaves the kiosk pending', function () {
    $supplier = verifiedSupplierUser();
    $kiosk = Kiosk::factory()->create(['supplier_id' => $supplier->id]);

    OtpCode::create([
        'identifier' => $supplier->email,
        'code' => Hash::make('112233'),
        'type' => 'kiosk_confirmation',
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($supplier->user)->post("/supplier/kiosks/{$kiosk->uuid}/confirm", ['code' => '999999'])
        ->assertSessionHasErrors('code');

    expect($kiosk->fresh()->isConfirmed())->toBeFalse();
});

test('a second kiosk over the free cap is flagged for admin approval and stays invisible even once confirmed', function () {
    Mail::fake();
    $supplier = verifiedSupplierUser();
    Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

    $this->actingAs($supplier->user)->post('/supplier/kiosks', kioskPayload())
        ->assertSessionHasNoErrors();

    $second = Kiosk::where('supplier_id', $supplier->id)->latest('id')->first();
    expect($second->requires_admin_approval)->toBeTrue();

    // the store() call above already generated a real (random) code - clear it so the
    // fixed test code below is unambiguously the "latest" one OtpService::verify() finds
    OtpCode::where('identifier', $supplier->email)->where('type', 'kiosk_confirmation')->delete();

    OtpCode::create([
        'identifier' => $supplier->email,
        'code' => Hash::make('445566'),
        'type' => 'kiosk_confirmation',
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($supplier->user)->post("/supplier/kiosks/{$second->uuid}/confirm", ['code' => '445566'])
        ->assertSessionHasNoErrors();

    $fresh = $second->fresh();
    expect($fresh->isConfirmed())->toBeTrue()
        ->and($fresh->isVisible())->toBeFalse();
});

test('admin approving a confirmed second kiosk makes it visible', function () {
    $supplier = verifiedSupplierUser();
    Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
    $second = Kiosk::factory()->create([
        'supplier_id' => $supplier->id,
        'requires_admin_approval' => true,
        'confirmed_at' => now(),
    ]);

    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-kiosks.approve');

    $this->actingAs($admin)->patch("/admin/marketplace/kiosks/{$second->uuid}/approve")
        ->assertSessionHasNoErrors();

    $fresh = $second->fresh();
    expect($fresh->admin_approved_at)->not->toBeNull()
        ->and($fresh->isVisible())->toBeTrue();
});

test('approving a second kiosk needs the approve permission', function () {
    $supplier = verifiedSupplierUser();
    $second = Kiosk::factory()->create([
        'supplier_id' => $supplier->id,
        'requires_admin_approval' => true,
        'confirmed_at' => now(),
    ]);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('marketplace-kiosks.view');

    $this->actingAs($viewer)->patch("/admin/marketplace/kiosks/{$second->uuid}/approve")->assertForbidden();
});
