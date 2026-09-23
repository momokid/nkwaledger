<?php

use App\Mail\ProductExpiringMail;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductExpiryAlertService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->service = app(ProductExpiryAlertService::class);
});

function kioskProductExpiringForSupplier(int $daysUntilExpiry): KioskProduct
{
    $user = User::factory()->create();
    $user->assignRole('supplier');
    $supplier = Supplier::factory()->verified()->create(['user_id' => $user->id]);
    $kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

    return KioskProduct::factory()->create([
        'kiosk_id' => $kiosk->id,
        'expiry_date' => now()->addDays($daysUntilExpiry)->toDateString(),
    ]);
}

test('a product expiring within the alert window gets an email and is marked alerted', function () {
    $product = kioskProductExpiringForSupplier(10);

    expect($this->service->sendDue())->toBe(1);

    Mail::assertSent(ProductExpiringMail::class);
    expect($product->fresh()->expiry_alerted_at)->not->toBeNull();
});

test('a product expiring well beyond the alert window is left alone', function () {
    $product = kioskProductExpiringForSupplier(90);

    expect($this->service->sendDue())->toBe(0);
    Mail::assertNothingSent();
});

test('an already-expired product is not alerted', function () {
    $product = KioskProduct::factory()->expired()->create();

    expect($this->service->sendDue())->toBe(0);
});

test('a product with no expiry date at all is never alerted', function () {
    $product = KioskProduct::factory()->create(['expiry_date' => null]);

    expect($this->service->sendDue())->toBe(0);
});

test('an already-alerted product is not alerted twice', function () {
    $product = kioskProductExpiringForSupplier(10);
    $product->update(['expiry_alerted_at' => now()->subDay()]);

    expect($this->service->sendDue())->toBe(0);
});

test('raising the alert-days setting brings a further-out expiry into the window', function () {
    $admin = User::factory()->create();
    app(\App\Services\SettingsService::class)->set('marketplace.product_expiry_alert_days', '45', $admin);

    $product = kioskProductExpiringForSupplier(40);

    expect($this->service->sendDue())->toBe(1);
});
