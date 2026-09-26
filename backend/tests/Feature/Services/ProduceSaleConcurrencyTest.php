<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\ProduceListing;
use App\Models\ProduceSale;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ProduceListingService;
use App\Services\ProduceSaleService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    AccountingPeriod::create([
        'name' => 'Current',
        'starts_on' => now()->startOfMonth(),
        'ends_on' => now()->endOfMonth(),
    ]);

    $this->sales = app(ProduceSaleService::class);
    $this->listings = app(ProduceListingService::class);

    $this->farmer = FarmerProfile::factory()->create();
    $farmType = FarmType::where('name', 'Maize')->firstOrFail();
    $unit = FarmUnit::factory()->approved()->create(['farm_type_id' => $farmType->id, 'farmer_profile_id' => $this->farmer->id]);
    $this->stock = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 20, 'current_quantity' => 20]);

    $this->listing = ProduceListing::factory()->create([
        'farm_unit_stock_id' => $this->stock->id,
        'farmer_profile_id' => $this->farmer->id,
        'quantity_listed' => 10,
        'quantity_remaining' => 10,
    ]);
});

// simulates two near-simultaneous callers (a double-tap, or a client retry) who both
// read the sale row BEFORE either tap actually lands - each holds its own stale PHP
// copy, exactly what two racing requests would each hold on two separate connections.
// without a row lock, confirm()/receive() trust that stale copy's own in-memory
// confirmed_at/received_at instead of re-checking the real, current row
test('a near-simultaneous confirm call working from a stale read cannot re-stamp an already-confirmed sale', function () {
    $sale = $this->sales->submit($this->listing, User::factory()->create(), '3', 'bank', '150');

    // both "callers" read the sale before either tap landed
    $staleA = ProduceSale::find($sale->id);
    $staleB = ProduceSale::find($sale->id);

    $this->sales->confirm($staleA, $this->farmer->user);

    // backdated so an overwrite is unmistakable, rather than relying on two calls a
    // few microseconds apart happening to land in different seconds
    $sale->fresh()->update(['confirmed_at' => now()->subHour()]);
    $realConfirmedAt = $sale->fresh()->confirmed_at;

    // B still believes confirmed_at is null - a locked, fresh re-read must stop it
    // from overwriting the real confirmation with a fresh "now()" timestamp
    $this->sales->confirm($staleB, $this->farmer->user);

    expect($sale->fresh()->confirmed_at->equalTo($realConfirmedAt))->toBeTrue();
});

test('two near-simultaneous receive taps on the same sale settle exactly once', function () {
    $buyer = User::factory()->create();
    $sale = $this->sales->submit($this->listing, $buyer, '3', 'bank', '150');
    $this->sales->confirm($sale->fresh(), $this->farmer->user);

    $staleA = ProduceSale::find($sale->id);
    $staleB = ProduceSale::find($sale->id);

    $this->sales->receive($staleA, $buyer);
    $this->sales->receive($staleB, $buyer);

    expect(Transaction::count())->toBe(1)
        ->and($this->listing->fresh()->quantity_remaining)->toEqualWithDelta(7.0, 0.01);
});

// Fix 2: two DIFFERENT sales settling against the same listing at the same instant.
// Each sale has its own idempotency key, so the ledger's unique constraint does not
// protect this case the way it protects a single sale settling twice - only locking
// quantity_remaining itself does. Simulated the same way: both reads happen before
// either write, exactly what reduceRemaining() sees mid-settlement for two sales
// whose maybeSettle() calls overlap
test('two different sales settling against the same listing never double-deduct stock', function () {
    $staleForSaleA = ProduceListing::find($this->listing->id);
    $staleForSaleB = ProduceListing::find($this->listing->id);

    $this->listings->reduceRemaining($staleForSaleA, 3.0);
    $this->listings->reduceRemaining($staleForSaleB, 4.0);

    // 10 - 3 - 4 = 3; a lost update would leave it at 6 (B overwriting A's result)
    expect($this->listing->fresh()->quantity_remaining)->toEqualWithDelta(3.0, 0.01);
});
