<?php

use App\Enums\KioskStatus;
use App\Models\Kiosk;

test('a kiosk is assigned a permanent NKL number derived from its own id', function () {
    $kiosk = Kiosk::factory()->create();

    expect($kiosk->fresh()->kiosk_number)->toBe(sprintf('NKL-%04d', $kiosk->id));
});

test('kiosk numbers are unique and sequential across kiosks', function () {
    $first = Kiosk::factory()->create();
    $second = Kiosk::factory()->create();

    expect($first->fresh()->kiosk_number)->not->toBe($second->fresh()->kiosk_number)
        ->and($second->id)->toBeGreaterThan($first->id);
});

test('a kiosk number is not mass-assignable, so an update leaves it untouched', function () {
    $kiosk = Kiosk::factory()->create();

    $kiosk->update(['kiosk_number' => 'NKL-9999']);

    expect($kiosk->fresh()->kiosk_number)->toBe(sprintf('NKL-%04d', $kiosk->id));
});

test('a kiosk number cannot be changed even by forcing the attribute directly', function () {
    $kiosk = Kiosk::factory()->create();

    expect(fn() => $kiosk->forceFill(['kiosk_number' => 'NKL-9999'])->save())
        ->toThrow(RuntimeException::class);
});

test('a new kiosk is not visible until it is active', function () {
    $kiosk = Kiosk::factory()->create();

    expect($kiosk->isVisible())->toBeFalse();
});

test('a confirmed, active kiosk is visible', function () {
    $kiosk = Kiosk::factory()->confirmed()->create();

    expect($kiosk->isVisible())->toBeTrue();
});

test('a kiosk requiring admin approval is not fully confirmed by supplier confirmation alone', function () {
    $kiosk = Kiosk::factory()->create([
        'requires_admin_approval' => true,
        'confirmed_at' => now(),
        'status' => KioskStatus::PendingConfirmation,
    ]);

    expect($kiosk->isFullyConfirmed())->toBeFalse();
});
