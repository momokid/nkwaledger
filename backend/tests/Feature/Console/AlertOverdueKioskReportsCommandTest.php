<?php

use App\Models\Kiosk;
use App\Models\KioskReport;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

test('the command alerts admin for every overdue report and reports the count, without suspending anything', function () {
    $this->seed(PermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->givePermissionTo('marketplace-kiosks.suspend');

    $user = User::factory()->create();
    $user->assignRole('supplier');
    $supplier = Supplier::factory()->verified()->create(['user_id' => $user->id]);
    $kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
    KioskReport::factory()->withAdmin()->create([
        'kiosk_id' => $kiosk->id,
        'admin_due_at' => now()->subDay(),
    ]);

    $this->artisan('marketplace:alert-overdue-reports')
        ->expectsOutputToContain('Alerted admin on 1 overdue report(s).')
        ->assertExitCode(0);

    expect($kiosk->fresh()->status->value)->toBe('active');
});
