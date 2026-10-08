<?php

// a batch that has ended (for example one a cancelled purchase started) no longer shows in the
// lists of current stock on My Farm and on the admin and agent stock page. Its movements stay in
// the My Farm timeline with their tags, and no stock count changes.

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
    LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $control->id, 'subcategory_id' => $sub, 'type_id' => $type->id, 'is_settlement' => $settlement,
    ]);

    $cash = $account('Cash A/C', $assetSub->id, true);
    $livestock = $account('Livestock A/C', $assetSub->id);

    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->purchase = TransactionTemplate::create([
        'name' => 'purchase', 'slug' => 'purchase', 'transaction_type' => 'EXPENSE',
        'debit_account_id' => $livestock->id, 'credit_account_id' => $cash->id, 'settlement_side' => 'credit',
        'requires_farm_unit' => true, 'is_stock_purchase' => true, 'stock_source' => 'purchase',
    ]);
    TransactionTemplate::create(['name' => 'correction', 'slug' => 'correction', 'transaction_type' => 'ADJUSTMENT', 'debit_account_id' => $cash->id, 'credit_account_id' => $livestock->id, 'settlement_side' => 'none']);
    $this->cash = $cash;

    $this->clerk = User::factory()->create();
    $this->approver = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
    $this->unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);

    $this->buy = fn(string $quantity) => app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->purchase->id,
        amount: '800',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        farmUnitId: $this->unit->id,
        recordedBy: $this->clerk->id,
        quantityPurchased: $quantity,
        unitOfMeasure: 'birds',
    ));

    $this->cancel = function (Transaction $transaction) {
        $service = app(ReversalService::class);
        $service->approve($service->request($transaction, $this->clerk, 'Typed wrongly'), $this->approver);
    };

    $this->props = function ($user, string $url) {
        $props = null;

        $this->actingAs($user)->get($url)->assertOk()->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    };

    $this->myFarmUnit = fn() => ($this->props)($this->farmerUser, '/my-farm')['units'][0];
    $this->adminStocks = fn() => ($this->props)($this->admin, "/admin/farmers/{$this->profile->uuid}/units/{$this->unit->id}/stocks")['stocks'];
});

test('a batch ended by a cancelled purchase is not listed as current stock on My Farm or the stock page', function () {
    ($this->cancel)(($this->buy)('20'));

    expect([
        'my_farm_batches' => count(($this->myFarmUnit)()['stocks']),
        'admin_batches' => count(($this->adminStocks)()),
        'ended_in_database' => FarmUnitStock::where('farm_unit_id', $this->unit->id)->whereNotNull('ended_on')->count(),
    ])->toBe(['my_farm_batches' => 0, 'admin_batches' => 0, 'ended_in_database' => 1]);
});

test('the ended batch keeps its cancelled movement in the My Farm timeline', function () {
    ($this->cancel)(($this->buy)('20'));

    $timeline = collect(($this->myFarmUnit)()['timeline']);

    expect([
        'entries' => $timeline->count(),
        'cancelled' => $timeline->where('is_cancelled', true)->count(),
        'rejected' => $timeline->where('is_rejected', true)->count(),
    ])->toBe(['entries' => 1, 'cancelled' => 1, 'rejected' => 0]);
});

test('a batch that is still live stays listed next to an ended one, and no count changes', function () {
    $live = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 100, 'started_on' => now()->subMonth()]);
    $ended = FarmUnitStock::factory()->closed()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 40, 'started_on' => now()->subMonths(2)]);

    $unit = ($this->myFarmUnit)();

    expect([
        'listed' => collect($unit['stocks'])->pluck('id')->all(),
        'admin_listed' => collect(($this->adminStocks)())->pluck('id')->all(),
        'live_count' => (float) $live->fresh()->current_quantity,
        'ended_count' => (float) $ended->fresh()->current_quantity,
    ])->toBe([
        'listed' => [$live->id],
        'admin_listed' => [$live->id],
        'live_count' => 100.0,
        'ended_count' => 40.0,
    ]);
});

test('a rejected movement on a live batch keeps its rejected tag in the list', function () {
    $live = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 100]);
    ($this->buy)('20');
    FarmUnitStockMovement::where('reason', MovementReason::Purchase)->firstOrFail()->reject($this->approver->id, 'Wrong number');

    $movement = collect(($this->myFarmUnit)()['stocks'][0]['movements'])->firstWhere('reason', 'Bought more');

    expect([$movement['is_rejected'], $movement['is_cancelled']])->toBe([true, false]);
});
