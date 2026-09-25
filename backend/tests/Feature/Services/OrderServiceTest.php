<?php

use App\Enums\CommissionStatus;
use App\Enums\KioskProductStatus;
use App\Enums\OrderStatus;
use App\Models\AccountingPeriod;
use App\Models\CatalogProduct;
use App\Models\Commission;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use App\Services\KioskSearchService;
use App\Services\OrderService;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RegionDistrictSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(RegionDistrictSeeder::class);

    AccountingPeriod::create([
        'name' => 'Current',
        'starts_on' => now()->startOfMonth(),
        'ends_on' => now()->endOfMonth(),
    ]);

    $this->service = app(OrderService::class);

    $this->supplierUser = User::factory()->create();
    $this->supplier = Supplier::factory()->create(['user_id' => $this->supplierUser->id]);
    $this->kiosk = Kiosk::factory()->confirmed()->create(['supplier_id' => $this->supplier->id]);

    $this->farmerUser = User::factory()->create();
    $this->farmer = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
    $this->farmUnit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->farmer->id]);

    $catalogProduct = CatalogProduct::factory()->create();
    $this->kioskProduct = KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $this->kiosk->id,
        'catalog_product_id' => $catalogProduct->id,
        'price' => 1000,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
    ]);
});

function submitOrder(): Order
{
    return test()->service->submitCart(
        farmer: test()->farmer,
        kiosk: test()->kiosk,
        items: [['kiosk_product_id' => test()->kioskProduct->id, 'quantity' => 2]],
        paymentMethod: 'bank',
        farmUnitId: test()->farmUnit->id,
    );
}

test('submitting a cart requires the chosen farm unit and snapshots the price', function () {
    $order = submitOrder();

    expect($order->farm_unit_id)->toBe($this->farmUnit->id)
        ->and($order->amount_minor)->toBe(2000)
        ->and($order->items()->first()->unit_price_at_order_time)->toBe(1000)
        ->and($order->status)->toBe(OrderStatus::Requested)
        ->and($order->events()->where('event_type', 'requested')->exists())->toBeTrue();
});

test('a supplier cannot order from their own kiosk', function () {
    $ownFarmer = FarmerProfile::factory()->create(['user_id' => $this->supplierUser->id]);
    $ownFarmUnit = FarmUnit::factory()->create(['farmer_profile_id' => $ownFarmer->id]);

    expect(fn() => $this->service->submitCart(
        farmer: $ownFarmer,
        kiosk: $this->kiosk,
        items: [['kiosk_product_id' => $this->kioskProduct->id, 'quantity' => 1]],
        paymentMethod: 'bank',
        farmUnitId: $ownFarmUnit->id,
    ))->toThrow(InvalidArgumentException::class);
});

test('confirm then receive posts to the ledger exactly once', function () {
    $order = submitOrder();

    $order = $this->service->confirm($order, $this->supplierUser);
    expect($order->ledger_transaction_id)->toBeNull();

    $order = $this->service->receive($order, $this->farmerUser);

    expect($order->ledger_transaction_id)->not->toBeNull();

    $transaction = $order->ledgerTransaction;
    expect($transaction->transaction_type)->toBe('EXPENSE')
        ->and($transaction->amount_minor)->toBe(2000)
        ->and($transaction->farm_unit_id)->toBe($this->farmUnit->id)
        ->and($transaction->is_provisional)->toBeFalse();
});

test('receive then confirm posts to the ledger exactly once too', function () {
    $order = submitOrder();

    $order = $this->service->receive($order, $this->farmerUser);
    expect($order->ledger_transaction_id)->toBeNull();

    $order = $this->service->confirm($order, $this->supplierUser);

    expect($order->ledger_transaction_id)->not->toBeNull();
});

test('a supplier cannot review their own order', function () {
    $order = submitOrder();
    $this->service->confirm($order, $this->supplierUser);
    $order = $this->service->receive($order, $this->farmerUser);

    expect(fn() => $this->service->review($order, $this->supplierUser, 5))
        ->toThrow(InvalidArgumentException::class);
});

test('only one review is allowed per order', function () {
    $order = submitOrder();
    $this->service->confirm($order, $this->supplierUser);
    $order = $this->service->receive($order, $this->farmerUser);

    $this->service->review($order, $this->farmerUser, 5, 'Great kiosk');

    expect(fn() => $this->service->review($order, $this->farmerUser, 3))
        ->toThrow(InvalidArgumentException::class);
});

test('an order not yet fully confirmed cannot be reviewed', function () {
    $order = submitOrder();
    $this->service->confirm($order, $this->supplierUser);

    expect(fn() => $this->service->review($order->fresh(), $this->farmerUser, 5))
        ->toThrow(InvalidArgumentException::class);
});

test('an unconfirmed order closes automatically after the settings-driven window and never posts', function () {
    $days = app(SettingsService::class)->getInt('marketplace.buyer_confirmation_days');

    $order = submitOrder();
    $order->forceFill(['requested_at' => now()->subDays($days + 1)])->save();

    // only confirmed, never received - still stale enough to close
    $this->service->confirm($order->fresh(), $this->supplierUser);
    $order->forceFill(['requested_at' => now()->subDays($days + 1)])->save();

    $closed = $this->service->closeUnconfirmed();

    $order->refresh();

    expect($closed)->toBe(1)
        ->and($order->closed_at)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Closed)
        ->and($order->ledger_transaction_id)->toBeNull();
});

test('a late tap on a closed order does nothing', function () {
    $order = submitOrder();
    $order->forceFill(['closed_at' => now()])->save();
    $order->recomputeStatus();
    $order->save();

    $this->service->confirm($order->fresh(), $this->supplierUser);
    $this->service->receive($order->fresh(), $this->farmerUser);

    $order->refresh();

    expect($order->confirmed_at)->toBeNull()
        ->and($order->received_at)->toBeNull()
        ->and($order->ledger_transaction_id)->toBeNull();
});

test('a facilitating agent creates a pending commission recording whether they verify the farmer', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');
    $this->farmer->update(['assigned_agent_id' => $agent->id]);

    $order = $this->service->submitCart(
        farmer: $this->farmer,
        kiosk: $this->kiosk,
        items: [['kiosk_product_id' => $this->kioskProduct->id, 'quantity' => 1]],
        paymentMethod: 'cod',
        farmUnitId: $this->farmUnit->id,
        facilitatingAgentId: $agent->id,
    );

    $commission = Commission::where('order_id', $order->id)->first();

    expect($commission)->not->toBeNull()
        ->and($commission->status)->toBe(CommissionStatus::PendingAdmin)
        ->and($commission->verifies_farmer)->toBeTrue();
});

test('no commission is created when no agent facilitated the order', function () {
    $order = submitOrder();

    expect(Commission::where('order_id', $order->id)->exists())->toBeFalse();
});

test('sales_count and rating on the kiosk search results reflect a real completed order', function () {
    $order = submitOrder();
    $this->service->confirm($order, $this->supplierUser);
    $order = $this->service->receive($order, $this->farmerUser);
    $this->service->review($order, $this->farmerUser, 4, 'Solid');

    $results = app(KioskSearchService::class)->rank($this->farmer);
    $row = $results->firstWhere('uuid', $this->kiosk->uuid);

    expect($row['sales_count'])->toBe(1)
        ->and($row['rating_average'])->toBe(4.0)
        ->and($row['review_count'])->toBe(1);
});
