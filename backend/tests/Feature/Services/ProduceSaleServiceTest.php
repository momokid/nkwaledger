<?php

use App\Enums\ProduceListingStatus;
use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\ProduceListing;
use App\Models\ProduceSale;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ProduceSaleService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    AccountingPeriod::create([
        'name' => 'Current',
        'starts_on' => now()->startOfMonth(),
        'ends_on' => now()->endOfMonth(),
    ]);

    $this->service = app(ProduceSaleService::class);

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

    $this->buyer = User::factory()->create();
});

test('a buyer cannot buy their own produce listing', function () {
    expect(fn() => $this->service->submit($this->listing, $this->farmer->user, '2', 'bank', '100'))
        ->toThrow(InvalidArgumentException::class);
});

test('a sale settles only after both taps land, confirm then receive', function () {
    $sale = $this->service->submit($this->listing, $this->buyer, '3', 'bank', '150');

    expect($sale->ledger_transaction_id)->toBeNull();

    $this->service->confirm($sale, $this->farmer->user);
    expect($sale->fresh()->ledger_transaction_id)->toBeNull();

    $settled = $this->service->receive($sale->fresh(), $this->buyer);

    expect($settled->ledger_transaction_id)->not->toBeNull()
        ->and(Transaction::find($settled->ledger_transaction_id)->amount_minor)->toBe(15000)
        ->and($this->listing->fresh()->quantity_remaining)->toEqualWithDelta(7.0, 0.01);
});

test('a sale settles only after both taps land, receive then confirm', function () {
    $sale = $this->service->submit($this->listing, $this->buyer, '3', 'bank', '150');

    $this->service->receive($sale, $this->buyer);
    expect($sale->fresh()->ledger_transaction_id)->toBeNull();

    $settled = $this->service->confirm($sale->fresh(), $this->farmer->user);

    expect($settled->ledger_transaction_id)->not->toBeNull()
        ->and($this->listing->fresh()->quantity_remaining)->toEqualWithDelta(7.0, 0.01);
});

test('a sale never settles twice, whichever tap lands last', function () {
    $sale = $this->service->submit($this->listing, $this->buyer, '3', 'bank', '150');

    $this->service->confirm($sale, $this->farmer->user);
    $settled = $this->service->receive($sale->fresh(), $this->buyer);
    $transactionId = $settled->ledger_transaction_id;

    // a late repeat of the tap must not post a second time or reduce stock twice
    $this->service->receive($settled->fresh(), $this->buyer);

    expect(Transaction::count())->toBe(1)
        ->and($settled->fresh()->ledger_transaction_id)->toBe($transactionId)
        ->and($this->listing->fresh()->quantity_remaining)->toEqualWithDelta(7.0, 0.01);
});

test('a sale only counts toward credit once it is fully confirmed AND an agent has co-confirmed it', function () {
    $sale = $this->service->submit($this->listing, $this->buyer, '3', 'bank', '150');
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    // posted to the ledger, but no agent has vouched for it yet
    $this->service->confirm($sale, $this->farmer->user);
    $settled = $this->service->receive($sale->fresh(), $this->buyer);

    expect($settled->fresh()->countsTowardCredit())->toBeFalse();

    // admin "seeing" the sale (it simply exists, visible in a query) changes nothing -
    // only the agent's own action does
    expect(ProduceSale::find($settled->id)->countsTowardCredit())->toBeFalse();

    $coConfirmed = $this->service->coConfirm($settled->fresh(), $agent);

    expect($coConfirmed->countsTowardCredit())->toBeTrue();
});

test('an unconfirmed sale closes after the confirmation window and never settles', function () {
    $sale = $this->service->submit($this->listing, $this->buyer, '3', 'bank', '150');
    $sale->update(['requested_at' => now()->subDays(30)]);

    $closed = $this->service->closeUnconfirmed();

    expect($closed)->toBe(1)
        ->and($sale->fresh()->closed_at)->not->toBeNull()
        ->and($sale->fresh()->ledger_transaction_id)->toBeNull();
});
