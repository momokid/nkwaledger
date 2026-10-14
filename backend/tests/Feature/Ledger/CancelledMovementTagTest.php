<?php

// a stock movement of a cancelled record is tagged "cancelled"; "rejected" stays for movements a
// checker sent back. Either way the stock is restored exactly once.

use App\Enums\MovementReason;
use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\ReversalService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);

    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);

    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $control->id, 'subcategory_id' => $sub, 'type_id' => $type->id, 'is_settlement' => $settlement,
    ]);

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $sales = $account('Sales A/C', $incomeSub->id);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->produceSale = TransactionTemplate::create([
        'name' => 'produce_sale', 'slug' => 'produce_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
        'requires_farm_unit' => true, 'is_produce_sale' => true,
    ]);

    TransactionTemplate::create([
        'name' => 'correction', 'slug' => 'correction', 'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'none',
    ]);

    $this->clerk = User::factory()->create();
    $this->approver = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    $this->unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);
    $this->stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 100]);

    $this->sell = fn(string $quantity) => app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->produceSale->id,
        amount: '500',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        farmUnitId: $this->unit->id,
        recordedBy: $this->clerk->id,
        quantitySold: $quantity,
    ));

    $this->cancel = function (Transaction $transaction) {
        $service = app(ReversalService::class);
        $service->approve($service->request($transaction, $this->clerk, 'Typed wrongly'), $this->approver);
    };

    $this->onHand = fn() => (float) $this->stock->fresh()->current_quantity;

    $this->soldMovement = fn() => FarmUnitStockMovement::where('reason', MovementReason::Sale)->firstOrFail();

    // what My Farm sends for the sale movement: batch list and timeline
    $this->myFarmView = function () {
        $props = null;

        $this->actingAs($this->farmerUser)->get('/my-farm')->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        $unit = $props['units'][0];
        $batch = collect($unit['stocks'][0]['movements'])->firstWhere('reason', 'Sold');
        $timeline = collect($unit['timeline'])->firstWhere('label', 'Sold');

        return [
            'batch' => ['rejected' => $batch['is_rejected'], 'cancelled' => $batch['is_cancelled']],
            'timeline' => ['rejected' => $timeline['is_rejected'], 'cancelled' => $timeline['is_cancelled']],
        ];
    };

    $this->adminView = function () {
        $props = null;

        $this->actingAs($this->admin)->get("/admin/farmers/{$this->profile->uuid}/units/{$this->unit->id}/stocks")->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        $row = collect($props['stocks'][0]['movements'])->firstWhere('reason', 'Sold');

        return ['rejected' => $row['is_rejected'], 'cancelled' => $row['is_cancelled']];
    };

    $this->bothFlags = ['rejected' => false, 'cancelled' => false];
});

test('a sale that is not cancelled shows neither tag and the count is unchanged', function () {
    ($this->sell)('10');

    expect([
        'my_farm' => ($this->myFarmView)(),
        'admin' => ($this->adminView)(),
        'on_hand' => ($this->onHand)(),
    ])->toBe([
        'my_farm' => ['batch' => $this->bothFlags, 'timeline' => $this->bothFlags],
        'admin' => $this->bothFlags,
        'on_hand' => 90.0,
    ]);
});

test('the movement of a cancelled record shows as cancelled, not rejected', function () {
    ($this->cancel)(($this->sell)('10'));

    $cancelled = ['rejected' => false, 'cancelled' => true];

    expect([
        'my_farm' => ($this->myFarmView)(),
        'admin' => ($this->adminView)(),
    ])->toBe([
        'my_farm' => ['batch' => $cancelled, 'timeline' => $cancelled],
        'admin' => $cancelled,
    ]);
});

test('a movement an admin rejected still shows as rejected', function () {
    ($this->sell)('10');
    ($this->soldMovement)()->reject($this->approver->id, 'Wrong number');

    $rejected = ['rejected' => true, 'cancelled' => false];

    expect([
        'my_farm' => ($this->myFarmView)(),
        'admin' => ($this->adminView)(),
        'on_hand' => ($this->onHand)(),
    ])->toBe([
        'my_farm' => ['batch' => $rejected, 'timeline' => $rejected],
        'admin' => $rejected,
        'on_hand' => 100.0,
    ]);
});

test('stock comes back once when an admin rejects the original movement after the record was cancelled', function () {
    ($this->cancel)(($this->sell)('10'));
    $afterCancel = ($this->onHand)();

    ($this->soldMovement)()->reject($this->approver->id, 'Wrong number');

    $movement = ($this->soldMovement)();

    expect([
        'after_cancel' => $afterCancel,
        'after_admin_rejection' => ($this->onHand)(),
        'still_cancelled' => $movement->isCancelled(),
        'rejection_reason_kept_out' => $movement->rejection_reason,
    ])->toBe(['after_cancel' => 100.0, 'after_admin_rejection' => 100.0, 'still_cancelled' => true, 'rejection_reason_kept_out' => null]);
});

test('stock comes back once when the record is cancelled after an admin rejected its movement', function () {
    $sale = ($this->sell)('10');
    ($this->soldMovement)()->reject($this->approver->id, 'Wrong number');
    $afterRejection = ($this->onHand)();

    ($this->cancel)($sale);

    expect([
        'after_rejection' => $afterRejection,
        'after_cancel' => ($this->onHand)(),
        'tag' => ($this->adminView)(),
    ])->toBe(['after_rejection' => 100.0, 'after_cancel' => 100.0, 'tag' => ['rejected' => true, 'cancelled' => false]]);
});
