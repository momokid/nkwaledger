<?php

use App\Enums\ProduceListingStatus;
use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\LedgerAccount;
use App\Models\ProduceListing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ProduceListingService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    AccountingPeriod::create([
        'name' => 'Current',
        'starts_on' => now()->startOfMonth(),
        'ends_on' => now()->endOfMonth(),
    ]);

    $this->service = app(ProduceListingService::class);
});

function cropStock(FarmerProfile $farmer, int $quantity = 100): FarmUnitStock
{
    $farmType = FarmType::where('name', 'Maize')->firstOrFail();
    $unit = FarmUnit::factory()->approved()->create(['farm_type_id' => $farmType->id, 'farmer_profile_id' => $farmer->id]);

    return FarmUnitStock::factory()->create([
        'farm_unit_id' => $unit->id,
        'opening_quantity' => $quantity,
        'current_quantity' => $quantity,
    ]);
}

function livestockStock(FarmerProfile $farmer, int $quantity = 50): FarmUnitStock
{
    $farmType = FarmType::where('name', 'Layers')->firstOrFail();
    $unit = FarmUnit::factory()->approved()->create(['farm_type_id' => $farmType->id, 'farmer_profile_id' => $farmer->id]);

    return FarmUnitStock::factory()->create([
        'farm_unit_id' => $unit->id,
        'opening_quantity' => $quantity,
        'current_quantity' => $quantity,
    ]);
}

test('a listing cannot claim more than the batch actually has confirmed', function () {
    $farmer = FarmerProfile::factory()->create();
    $stock = cropStock($farmer, 20);
    $user = User::factory()->create();
    $user->assignRole('farmer');

    expect(fn() => $this->service->create($stock, $farmer, $user, 21, null, null))
        ->toThrow(InvalidArgumentException::class);

    $ok = $this->service->create($stock, $farmer, $user, 20, null, null);
    expect($ok->quantity_listed)->toEqual('20.00');
});

// this proves the cap is enforced cumulatively against what is ALREADY claimed, using
// the same locked read-then-check path a real concurrent attempt would hit - two
// processes racing each other is not reproducible inside a single Pest test/sqlite
// connection, but the enforcement logic under the lock is exactly what runs either way
test('two listing attempts against the same batch can never jointly exceed it', function () {
    $farmer = FarmerProfile::factory()->create();
    $stock = cropStock($farmer, 30);
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $first = $this->service->create($stock, $farmer, $user, 20, null, null);
    expect($first->status)->toBe(ProduceListingStatus::Active);

    // only 10 left (30 - 20 already active) - asking for 15 must fail, not silently clamp
    expect(fn() => $this->service->create($stock, $farmer, $user, 15, null, null))
        ->toThrow(InvalidArgumentException::class);

    $second = $this->service->create($stock, $farmer, $user, 10, null, null);
    expect($second->quantity_listed)->toEqual('10.00');

    $totalClaimed = ProduceListing::where('farm_unit_stock_id', $stock->id)->sum('quantity_remaining');
    expect((float) $totalClaimed)->toBeLessThanOrEqual((float) $stock->fresh()->current_quantity);
});

test('an agent-posted listing stays a draft until the farmer agrees', function () {
    $farmer = FarmerProfile::factory()->create();
    $stock = cropStock($farmer, 20);
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    $listing = $this->service->create($stock, $farmer, $agent, 10, null, null);

    expect($listing->status)->toBe(ProduceListingStatus::Draft)
        ->and($listing->farmer_agreed_at)->toBeNull();

    $agreed = $this->service->agree($listing);

    expect($agreed->status)->toBe(ProduceListingStatus::Active)
        ->and($agreed->farmer_agreed_at)->not->toBeNull();
});

test('withdrawing a listing posts nothing to the ledger', function () {
    $farmer = FarmerProfile::factory()->create();
    $stock = cropStock($farmer, 20);
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $listing = $this->service->create($stock, $farmer, $user, 10, null, null);

    $this->service->withdraw($listing);

    expect($listing->fresh()->status)->toBe(ProduceListingStatus::Withdrawn)
        ->and(Transaction::count())->toBe(0);
});

test('marking sold posts the farmer income and reduces stock, never price times quantity', function () {
    $farmer = FarmerProfile::factory()->create();
    $stock = cropStock($farmer, 20);
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $listing = $this->service->create($stock, $farmer, $user, 10, null, null);

    $cash = LedgerAccount::where('name', 'Cash A/C')->firstOrFail();

    $transaction = $this->service->markSold($listing, '500', '4', $cash->id, $user->id);

    // 4 units sold at a typed 500 cedis - the app never multiplies price by quantity,
    // so the posted amount is exactly the typed figure, not 4 * anything
    expect($transaction->amount_minor)->toBe(50000)
        ->and($stock->fresh()->current_quantity)->toEqualWithDelta(16.0, 0.01)
        ->and($listing->fresh()->quantity_remaining)->toEqualWithDelta(6.0, 0.01)
        ->and($listing->fresh()->status)->toBe(ProduceListingStatus::Active);
});

test('a partial sale is allowed and does not close the listing', function () {
    $farmer = FarmerProfile::factory()->create();
    $stock = cropStock($farmer, 20);
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $listing = $this->service->create($stock, $farmer, $user, 10, null, null);
    $cash = LedgerAccount::where('name', 'Cash A/C')->firstOrFail();

    $this->service->markSold($listing, '200', '3', $cash->id, $user->id);

    expect($listing->fresh()->quantity_remaining)->toEqualWithDelta(7.0, 0.01)
        ->and($listing->fresh()->status)->toBe(ProduceListingStatus::Active);
});

test('selling the last of a listing closes it as sold', function () {
    $farmer = FarmerProfile::factory()->create();
    $stock = cropStock($farmer, 20);
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $listing = $this->service->create($stock, $farmer, $user, 5, null, null);
    $cash = LedgerAccount::where('name', 'Cash A/C')->firstOrFail();

    $this->service->markSold($listing, '100', '5', $cash->id, $user->id);

    expect($listing->fresh()->quantity_remaining)->toEqualWithDelta(0.0, 0.01)
        ->and($listing->fresh()->status)->toBe(ProduceListingStatus::Sold);
});

test('a crop listing is a crop, a livestock listing is not', function () {
    $farmer = FarmerProfile::factory()->create();
    $cropListing = ProduceListing::factory()->create(['farm_unit_stock_id' => cropStock($farmer)->id]);
    $animalListing = ProduceListing::factory()->create(['farm_unit_stock_id' => livestockStock($farmer)->id]);

    expect($cropListing->isCrop())->toBeTrue()
        ->and($animalListing->isCrop())->toBeFalse();
});

test('reconcileStock reduces a listing when its batch stock has shrunk below what is claimed', function () {
    $farmer = FarmerProfile::factory()->create();
    $stock = cropStock($farmer, 20);
    $listing = ProduceListing::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'quantity_listed' => 15,
        'quantity_remaining' => 15,
    ]);

    // the batch lost animals/produce after the listing already claimed 15 of it
    $stock->update(['current_quantity' => 8]);

    $affected = $this->service->reconcileStock();

    expect($affected)->toBe(1)
        ->and($listing->fresh()->quantity_remaining)->toEqualWithDelta(8.0, 0.01);
});

test('a crop listing gets one reminder before expiring, then expires on its date', function () {
    $farmer = FarmerProfile::factory()->create();
    $listing = ProduceListing::factory()->create([
        'farm_unit_stock_id' => cropStock($farmer)->id,
        'expires_at' => now()->addDays(2),
        'crop_expiry_days' => 5,
    ]);

    $reminded = $this->service->sendExpiryReminders();
    expect($reminded)->toBe(1)
        ->and($listing->fresh()->expiry_reminder_sent_at)->not->toBeNull()
        ->and($listing->fresh()->status)->toBe(ProduceListingStatus::Active);

    // sending reminders again must not double-send
    expect($this->service->sendExpiryReminders())->toBe(0);

    $listing->update(['expires_at' => now()->subDay()]);
    $expired = $this->service->expireDueCrops();

    expect($expired)->toBe(1)
        ->and($listing->fresh()->status)->toBe(ProduceListingStatus::Expired);
});

test('a livestock listing never auto-expires, whatever the crop-expiry sweep does', function () {
    $farmer = FarmerProfile::factory()->create();
    $listing = ProduceListing::factory()->create([
        'farm_unit_stock_id' => livestockStock($farmer)->id,
        'expires_at' => null,
    ]);

    $this->service->expireDueCrops();

    expect($listing->fresh()->status)->toBe(ProduceListingStatus::Active);
});

test('a non-expiring listing is prompted, then hidden if nobody answers', function () {
    $farmer = FarmerProfile::factory()->create();
    $listing = ProduceListing::factory()->create([
        'farm_unit_stock_id' => livestockStock($farmer)->id,
        'expires_at' => null,
        'created_at' => now()->subDays(20),
    ]);

    $prompted = $this->service->promptStillAvailable();
    expect($prompted)->toBe(1)
        ->and($listing->fresh()->still_available_prompted_at)->not->toBeNull();

    // not enough silence has passed yet
    expect($this->service->hideSilentListings())->toBe(0);

    $listing->update(['still_available_prompted_at' => now()->subDays(20)]);

    $hidden = $this->service->hideSilentListings();
    expect($hidden)->toBe(1)
        ->and($listing->fresh()->status)->toBe(ProduceListingStatus::Hidden);
});
