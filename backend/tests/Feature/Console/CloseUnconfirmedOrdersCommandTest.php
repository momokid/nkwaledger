<?php

use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\Kiosk;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;

test('the command closes every order stale past the confirmation window and reports the count', function () {
    $user = User::factory()->create();
    $user->assignRole('supplier');
    $supplier = Supplier::factory()->create(['user_id' => $user->id]);
    $kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

    $farmer = FarmerProfile::factory()->create();
    $farmUnit = FarmUnit::factory()->create(['farmer_profile_id' => $farmer->id]);

    // stale: requested 20 days ago (default window is 15), never confirmed or received
    $stale = Order::factory()->create([
        'kiosk_id' => $kiosk->id,
        'farmer_profile_id' => $farmer->id,
        'farm_unit_id' => $farmUnit->id,
        'requested_at' => now()->subDays(20),
    ]);

    // fresh: requested today, should not close
    Order::factory()->create([
        'kiosk_id' => $kiosk->id,
        'farmer_profile_id' => $farmer->id,
        'farm_unit_id' => $farmUnit->id,
        'requested_at' => now(),
    ]);

    $this->artisan('marketplace:close-unconfirmed-orders')
        ->expectsOutputToContain('Closed 1 unconfirmed order(s).')
        ->assertExitCode(0);

    expect($stale->fresh()->closed_at)->not->toBeNull();
});
