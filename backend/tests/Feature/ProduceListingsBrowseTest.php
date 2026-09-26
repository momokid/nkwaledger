<?php

use App\Enums\ProduceListingStatus;
use App\Models\AccountingPeriod;
use App\Models\ContactRequest;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\ProduceListing;
use App\Models\ProduceSale;
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
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 20, 'current_quantity' => 20]);

    $this->listing = ProduceListing::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'farmer_profile_id' => $this->farmer->id,
        'posted_by_user_id' => $this->farmerUser->id,
        'quantity_listed' => 10,
        'quantity_remaining' => 10,
    ]);

    $this->buyer = User::factory()->create();
});

test('any authenticated account can view a produce listing, not only farmers', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get("/produce-listings/{$this->listing->uuid}")
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('ProduceListings/Show')->where('listing.uuid', $this->listing->uuid));
});

test('an interest request resolves to whoever posted the listing, without ever exposing a phone number', function () {
    $this->actingAs($this->buyer)->post("/produce-listings/{$this->listing->uuid}/interest", [
        'message' => 'Is this still fresh?',
    ])->assertSessionHasNoErrors();

    $request = ContactRequest::first();

    expect($request)->not->toBeNull()
        ->and($request->contactable_type)->toBe((new ProduceListing())->getMorphClass())
        ->and($request->contactable_id)->toBe($this->listing->id)
        ->and($request->requester_user_id)->toBe($this->buyer->id)
        ->and($request->recipient_user_id)->toBe($this->farmerUser->id);
});

test('a buyer offer creates a produce sale, and it settles only once both taps land', function () {
    $this->actingAs($this->buyer)->post("/produce-listings/{$this->listing->uuid}/sales", [
        'quantity' => 3,
        'payment_method' => 'bank',
        'amount' => 150,
    ])->assertSessionHasNoErrors();

    $sale = ProduceSale::first();
    expect($sale)->not->toBeNull()
        ->and($sale->amount_minor)->toBe(15000)
        ->and($sale->ledger_transaction_id)->toBeNull();

    $this->actingAs($this->farmerUser)->post("/my-listings/sales/{$sale->uuid}/confirm")->assertSessionHasNoErrors();
    expect($sale->fresh()->ledger_transaction_id)->toBeNull();

    $this->actingAs($this->buyer)->post("/produce-listings/sales/{$sale->uuid}/receive")->assertSessionHasNoErrors();

    expect($sale->fresh()->ledger_transaction_id)->not->toBeNull()
        ->and($this->listing->fresh()->quantity_remaining)->toEqualWithDelta(7.0, 0.01);
});

test('only the buyer on a sale can tap receive', function () {
    $sale = ProduceSale::factory()->create([
        'produce_listing_id' => $this->listing->id,
        'buyer_user_id' => $this->buyer->id,
        'farmer_profile_id' => $this->farmer->id,
    ]);

    $someoneElse = User::factory()->create();

    $this->actingAs($someoneElse)->post("/produce-listings/sales/{$sale->uuid}/receive")->assertForbidden();
});

test('an agent co-confirms a sale for credit eligibility, and admin visibility never requires an approval action', function () {
    $sale = ProduceSale::factory()->fullyConfirmed()->create([
        'produce_listing_id' => $this->listing->id,
        'buyer_user_id' => $this->buyer->id,
        'farmer_profile_id' => $this->farmer->id,
    ]);

    $agent = User::factory()->create();
    $agent->assignRole('agent');

    expect($sale->fresh()->countsTowardCredit())->toBeFalse();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin/marketplace/produce-sales')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Admin/Marketplace/ProduceSales/Index')
            ->where('sales.data.0.counts_toward_credit', false));

    $this->actingAs($agent)->post("/agent/produce-sales/{$sale->uuid}/co-confirm")->assertSessionHasNoErrors();

    expect($sale->fresh()->countsTowardCredit())->toBeTrue();
});
