<?php

use App\Enums\KioskProductStatus;
use App\Enums\PriceHistoryType;
use App\Models\KioskProduct;
use App\Models\User;

test('a kiosk product is available when in stock, active and not expired', function () {
    $product = KioskProduct::factory()->create();

    expect(KioskProduct::available()->find($product->id))->not->toBeNull();
});

test('an out-of-stock product is excluded from the available scope', function () {
    $product = KioskProduct::factory()->create(['in_stock' => false]);

    expect(KioskProduct::available()->find($product->id))->toBeNull();
});

test('a suspended product is excluded from the available scope', function () {
    $product = KioskProduct::factory()->create(['status' => KioskProductStatus::Suspended]);

    expect(KioskProduct::available()->find($product->id))->toBeNull();
});

test('an expired product is excluded from the available scope even though nothing ran a nightly job', function () {
    $product = KioskProduct::factory()->expired()->create();

    expect(KioskProduct::available()->find($product->id))->toBeNull();
});

test('a product with a future expiry date is still available', function () {
    $product = KioskProduct::factory()->create(['expiry_date' => now()->addWeek()->toDateString()]);

    expect(KioskProduct::available()->find($product->id))->not->toBeNull();
});

test('a product with no expiry date at all is available', function () {
    $product = KioskProduct::factory()->create(['expiry_date' => null]);

    expect(KioskProduct::available()->find($product->id))->not->toBeNull();
});

test('recording the initial price writes one changed row and confirms it immediately', function () {
    $user = User::factory()->create();
    $product = KioskProduct::factory()->create(['price' => 1000, 'price_confirmed_at' => null]);

    $product->recordInitialPrice($user);

    expect($product->priceHistory()->count())->toBe(1)
        ->and($product->priceHistory()->first()->old_price)->toBeNull()
        ->and($product->priceHistory()->first()->new_price)->toBe(1000)
        ->and($product->priceHistory()->first()->type)->toBe(PriceHistoryType::Changed)
        ->and($product->fresh()->price_confirmed_at)->not->toBeNull();
});

test('changing the price writes a changed row, updates the price, and resets staleness', function () {
    $user = User::factory()->create();
    $product = KioskProduct::factory()->stalePrice()->create(['price' => 1000]);

    $product->changePrice(1500, $user);

    $latest = $product->priceHistory()->latest('id')->first();

    expect($product->fresh()->price)->toBe(1500)
        ->and($latest->old_price)->toBe(1000)
        ->and($latest->new_price)->toBe(1500)
        ->and($latest->type)->toBe(PriceHistoryType::Changed)
        ->and($product->fresh()->stale_alerted_at)->toBeNull();
});

test('confirming a price unchanged writes a confirmed row without changing the price', function () {
    $user = User::factory()->create();
    $product = KioskProduct::factory()->stalePrice()->create(['price' => 1000]);

    $product->confirmPriceUnchanged($user);

    $latest = $product->priceHistory()->latest('id')->first();

    expect($product->fresh()->price)->toBe(1000)
        ->and($latest->old_price)->toBe(1000)
        ->and($latest->new_price)->toBe(1000)
        ->and($latest->type)->toBe(PriceHistoryType::Confirmed)
        ->and($product->fresh()->price_confirmed_at->isToday())->toBeTrue();
});

test('a price is stale once it has gone unconfirmed for the given number of days', function () {
    $product = KioskProduct::factory()->stalePrice(20)->create();

    expect($product->isPriceStale(15))->toBeTrue();
});

test('a recently confirmed price is not stale', function () {
    $product = KioskProduct::factory()->priceConfirmed()->create();

    expect($product->isPriceStale(15))->toBeFalse();
});

test('a product that has never had its price confirmed is stale', function () {
    $product = KioskProduct::factory()->create(['price_confirmed_at' => null]);

    expect($product->isPriceStale(15))->toBeTrue();
});
