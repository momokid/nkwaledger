<?php

use App\Models\Kiosk;
use App\Models\Supplier;
use App\Models\User;
use App\Services\KioskCapService;
use App\Services\SettingsService;

beforeEach(function () {
    $this->caps = app(KioskCapService::class);
});

test('a verified supplier registering their first kiosk does not exceed the cap', function () {
    $supplier = Supplier::factory()->verified()->create();

    expect($this->caps->exceedsCap($supplier))->toBeFalse();
});

test('a verified supplier with one already-registered kiosk exceeds the cap on a second', function () {
    $supplier = Supplier::factory()->verified()->create();
    Kiosk::factory()->create(['supplier_id' => $supplier->id]);

    expect($this->caps->exceedsCap($supplier->fresh()))->toBeTrue();
});

test('two unrelated verified suppliers each get their own free kiosk', function () {
    $first = Supplier::factory()->verified()->create();
    Kiosk::factory()->create(['supplier_id' => $first->id]);

    $second = Supplier::factory()->verified()->create();

    expect($this->caps->exceedsCap($second))->toBeFalse();
});

// a user's phone is already unique app-wide, so the only way an unverified identity could
// dodge the cap is by never verifying at all - which must not let its kiosk count for anything
test("an unverified supplier's existing kiosk does not count toward its own cap check", function () {
    $user = User::factory()->unverified()->create();
    $supplier = Supplier::factory()->create(['user_id' => $user->id]);
    Kiosk::factory()->create(['supplier_id' => $supplier->id]);

    expect($this->caps->exceedsCap($supplier->fresh()))->toBeFalse();
});

test('raising the kiosks-per-email-cap setting allows a second kiosk', function () {
    $admin = User::factory()->create();
    app(SettingsService::class)->set('marketplace.kiosks_per_email_cap', '2', $admin);

    $supplier = Supplier::factory()->verified()->create();
    Kiosk::factory()->create(['supplier_id' => $supplier->id]);

    expect($this->caps->exceedsCap($supplier->fresh()))->toBeFalse();
});
