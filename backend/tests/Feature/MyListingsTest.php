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
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    AccountingPeriod::create([
        'name' => 'Current',
        'starts_on' => now()->startOfMonth(),
        'ends_on' => now()->endOfMonth(),
    ]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->farmer = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    $farmType = FarmType::where('name', 'Maize')->firstOrFail();
    $unit = FarmUnit::factory()->approved()->create(['farm_type_id' => $farmType->id, 'farmer_profile_id' => $this->farmer->id]);
    $this->stock = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 20, 'current_quantity' => 20]);
});

test('a farmer can post a listing on their own batch', function () {
    $this->actingAs($this->farmerUser)->post('/my-listings', [
        'farm_unit_stock_id' => $this->stock->id,
        'quantity' => 10,
    ])->assertSessionHasNoErrors();

    $listing = ProduceListing::first();

    expect($listing)->not->toBeNull()
        ->and($listing->status)->toBe(ProduceListingStatus::Active)
        ->and((float) $listing->quantity_listed)->toBe(10.0);
});

test('the my-listings index only shows this farmer\'s own listings', function () {
    ProduceListing::factory()->create(['farm_unit_stock_id' => $this->stock->id, 'farmer_profile_id' => $this->farmer->id]);

    $otherFarmer = FarmerProfile::factory()->create();
    ProduceListing::factory()->create(['farmer_profile_id' => $otherFarmer->id]);

    $this->actingAs($this->farmerUser)->get('/my-listings')
        ->assertInertia(fn($page) => $page
            ->component('MyListings/Index')
            ->where('listings', fn($rows) => count($rows) === 1));
});

test('an agent-posted listing is invisible to browsing until the farmer agrees', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');
    $this->farmer->update(['assigned_agent_id' => $agent->id]);

    $this->actingAs($agent)->post("/agent/farmers/{$this->farmer->uuid}/listings", [
        'farm_unit_stock_id' => $this->stock->id,
        'quantity' => 10,
    ])->assertSessionHasNoErrors();

    $listing = ProduceListing::first();
    expect($listing->status)->toBe(ProduceListingStatus::Draft);

    // not yet visible on the public browse page
    $buyer = User::factory()->create();
    $this->actingAs($buyer)->get('/produce-listings')
        ->assertInertia(fn($page) => $page->where('listings.data', []));

    // the farmer agrees, and only then does it appear
    $this->actingAs($this->farmerUser)->post("/my-listings/{$listing->uuid}/agree")->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ProduceListingStatus::Active);

    $this->actingAs($buyer)->get('/produce-listings')
        ->assertInertia(fn($page) => $page->where('listings.data.0.uuid', $listing->fresh()->uuid));
});

test('withdrawing a listing removes it from the public browse page and posts nothing', function () {
    $listing = ProduceListing::factory()->create(['farm_unit_stock_id' => $this->stock->id, 'farmer_profile_id' => $this->farmer->id]);

    $this->actingAs($this->farmerUser)->post("/my-listings/{$listing->uuid}/withdraw")->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ProduceListingStatus::Withdrawn)
        ->and(Transaction::count())->toBe(0);

    $buyer = User::factory()->create();
    $this->actingAs($buyer)->get('/produce-listings')
        ->assertInertia(fn($page) => $page->where('listings.data', []));
});

test('marking a listing sold records a real transaction and reduces the batch stock', function () {
    $listing = ProduceListing::factory()->create([
        'farm_unit_stock_id' => $this->stock->id,
        'farmer_profile_id' => $this->farmer->id,
        'quantity_listed' => 10,
        'quantity_remaining' => 10,
    ]);
    $cash = LedgerAccount::where('name', 'Cash A/C')->firstOrFail();

    $this->actingAs($this->farmerUser)->post("/my-listings/{$listing->uuid}/mark-sold", [
        'amount' => 300,
        'quantity' => 4,
        'settlement_account_id' => $cash->id,
    ])->assertSessionHasNoErrors();

    expect(Transaction::first()->amount_minor)->toBe(30000)
        ->and($this->stock->fresh()->current_quantity)->toEqualWithDelta(16.0, 0.01)
        ->and($listing->fresh()->quantity_remaining)->toEqualWithDelta(6.0, 0.01);
});

test('a farmer cannot manage another farmer\'s listing', function () {
    $otherFarmer = FarmerProfile::factory()->create();
    $listing = ProduceListing::factory()->create(['farmer_profile_id' => $otherFarmer->id]);

    $this->actingAs($this->farmerUser)->post("/my-listings/{$listing->uuid}/withdraw")->assertForbidden();
});

test('an agent who is not this farmer\'s assigned agent cannot manage their listing', function () {
    $listing = ProduceListing::factory()->create(['farmer_profile_id' => $this->farmer->id]);

    $unassignedAgent = User::factory()->create();
    $unassignedAgent->assignRole('agent');

    $this->actingAs($unassignedAgent)->post("/my-listings/{$listing->uuid}/withdraw")->assertForbidden();
});
