<?php

use App\Models\Kiosk;
use App\Models\KioskReport;
use App\Models\Supplier;
use App\Models\User;

test('the command escalates every due-silent report and reports the count', function () {
    $user = User::factory()->create();
    $user->assignRole('supplier');
    $supplier = Supplier::factory()->verified()->create(['user_id' => $user->id]);
    $kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
    KioskReport::factory()->create(['kiosk_id' => $kiosk->id, 'supplier_due_at' => now()->subDay()]);

    $this->artisan('marketplace:escalate-silent-reports')
        ->expectsOutputToContain('Escalated 1 silent report(s) to admin.')
        ->assertExitCode(0);
});
