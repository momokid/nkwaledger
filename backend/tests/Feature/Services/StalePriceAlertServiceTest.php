<?php

use App\Mail\StalePriceMail;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\Notification;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StalePriceAlertService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    Mail::fake();
    $this->service = app(StalePriceAlertService::class);
});

function kioskProductForSupplier(): KioskProduct
{
    $user = User::factory()->create();
    $user->assignRole('supplier');
    $supplier = Supplier::factory()->verified()->create(['user_id' => $user->id]);
    $kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

    return KioskProduct::factory()->create(['kiosk_id' => $kiosk->id]);
}

test('a product stale beyond the setting gets an email, an in-app notice, and an admin alert', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-catalog.view');

    $product = kioskProductForSupplier();
    $product->update(['price_confirmed_at' => now()->subDays(20)]);

    $sent = $this->service->sendDue();

    expect($sent)->toBe(1);

    Mail::assertSent(StalePriceMail::class);

    expect(Notification::where('user_id', $product->kiosk->supplier->user_id)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $admin->id)->exists())->toBeTrue();

    expect($product->fresh()->stale_alerted_at)->not->toBeNull();
});

test('a recently confirmed price is left alone', function () {
    $product = kioskProductForSupplier();
    $product->update(['price_confirmed_at' => now()->subDays(2)]);

    expect($this->service->sendDue())->toBe(0);
    Mail::assertNothingSent();
});

test('an already-alerted stale product is not alerted again until confirmed', function () {
    $product = kioskProductForSupplier();
    $product->update([
        'price_confirmed_at' => now()->subDays(20),
        'stale_alerted_at' => now()->subDay(),
    ]);

    expect($this->service->sendDue())->toBe(0);
    Mail::assertNothingSent();
});

test('confirming the price clears the alert flag so a future staleness alerts again', function () {
    $product = kioskProductForSupplier();
    $product->update([
        'price_confirmed_at' => now()->subDays(20),
        'stale_alerted_at' => now()->subDay(),
    ]);

    $product->confirmPriceUnchanged($product->kiosk->supplier->user);

    expect($product->fresh()->stale_alerted_at)->toBeNull();
});

test('raising the stale-days setting means a product that was stale is no longer due', function () {
    $admin = User::factory()->create();
    app(\App\Services\SettingsService::class)->set('marketplace.price_stale_days', '30', $admin);

    $product = kioskProductForSupplier();
    $product->update(['price_confirmed_at' => now()->subDays(20)]);

    expect($this->service->sendDue())->toBe(0);
});
