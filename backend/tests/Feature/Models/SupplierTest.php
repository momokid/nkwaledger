<?php

use App\Models\Supplier;
use App\Models\User;

test('a new supplier is unverified', function () {
    $supplier = Supplier::factory()->create();

    expect($supplier->verificationStatus())->toBe('unverified')
        ->and($supplier->isVerifiedIdentity())->toBeFalse();
});

test('a supplier with only a verified email is still not a verified identity', function () {
    $user = User::factory()->unverified()->create();
    $supplier = Supplier::factory()->create(['user_id' => $user->id, 'email_verified_at' => now()]);

    expect($supplier->isVerifiedIdentity())->toBeFalse();
});

test('a supplier with a verified email and a verified phone is a verified identity', function () {
    $supplier = Supplier::factory()->verified()->create();

    expect($supplier->fresh()->isVerifiedIdentity())->toBeTrue()
        ->and($supplier->fresh()->verificationStatus())->toBe('email_and_phone_verified');
});

test('a suspended supplier reports itself as suspended', function () {
    $supplier = Supplier::factory()->suspended()->create();

    expect($supplier->isSuspended())->toBeTrue();
});
