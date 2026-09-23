<?php

use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('the command sends alerts for every due expiring product and reports the count', function () {
    Mail::fake();

    $user = User::factory()->create();
    $user->assignRole('supplier');
    $supplier = Supplier::factory()->verified()->create(['user_id' => $user->id]);
    $kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
    KioskProduct::factory()->create([
        'kiosk_id' => $kiosk->id,
        'expiry_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('marketplace:alert-expiring-products')
        ->expectsOutputToContain('Sent 1 expiry alert(s).')
        ->assertExitCode(0);
});
