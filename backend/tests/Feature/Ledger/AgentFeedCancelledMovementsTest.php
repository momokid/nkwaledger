<?php

// the agent activity feed lists the stock movements of a cancelled record, marked cancelled;
// movements a checker sent back stay hidden, and none of this changes any total.

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
use App\Services\Agent\AgentActivityFeedService;
use App\Services\Agent\FarmerRosterService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\ReversalService;

beforeEach(function () {
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

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->clerk = User::factory()->create();
    $this->approver = User::factory()->create();

    $this->profile = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $this->unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id]);
    FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 100]);

    $this->sell = fn(string $quantity, string $amount) => app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->produceSale->id,
        amount: $amount,
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

    $this->from = now()->subDays(6)->toDateString();
    $this->to = now()->toDateString();

    // quantity => is_cancelled for the "Sold" movements in the feed
    $this->soldInFeed = fn() => collect(app(AgentActivityFeedService::class)->recentFor($this->agent->id, $this->from, $this->to, 20))
        ->where('kind', 'stock_movement')
        ->where('reason', MovementReason::Sale->label())
        ->mapWithKeys(fn($entry) => [(string) (float) $entry['quantity'] => $entry['is_cancelled']])
        ->sortKeys()
        ->all();
});

test('a cancelled record shows its stock movements in the feed, marked cancelled', function () {
    ($this->sell)('5', '250');
    ($this->cancel)(($this->sell)('10', '500'));

    expect(($this->soldInFeed)())->toBe(['5' => false, '10' => true]);
});

test('a movement an admin rejected stays hidden from the feed', function () {
    ($this->sell)('5', '250');
    ($this->sell)('7', '350');
    FarmUnitStockMovement::where('reason', MovementReason::Sale)->where('quantity', 7)->firstOrFail()
        ->reject($this->approver->id, 'Wrong number');

    expect(($this->soldInFeed)())->toBe(['5' => false]);
});

test('a movement rejected by an admin after its record was cancelled is still listed as cancelled', function () {
    ($this->sell)('5', '250');
    ($this->cancel)(($this->sell)('10', '500'));
    FarmUnitStockMovement::where('reason', MovementReason::Sale)->where('quantity', 10)->firstOrFail()
        ->reject($this->approver->id, 'Wrong number');

    expect(($this->soldInFeed)())->toBe(['5' => false, '10' => true]);
});

test('cancelled movements and transactions add nothing to the feed side totals', function () {
    ($this->sell)('5', '250');
    ($this->cancel)(($this->sell)('10', '500'));

    [$income, $expense, $active, , $collected, $paidOut] = app(FarmerRosterService::class)
        ->totalsFor($this->agent->id, $this->from, $this->to);

    expect(compact('income', 'expense', 'active', 'collected', 'paidOut'))
        ->toBe(['income' => 25000, 'expense' => 0, 'active' => 1, 'collected' => 25000, 'paidOut' => 0]);
});
