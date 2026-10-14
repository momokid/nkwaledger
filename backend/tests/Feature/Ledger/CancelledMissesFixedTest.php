<?php

// places that used to count a cancelled record (and its correction row) and no longer do:
// the admin region detail, the "held back" figure on the statement and on the trial balance,
// and the provisional-record count in the approval queue.

use App\Models\AccountingPeriod;
use App\Models\Community;
use App\Models\District;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Region;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\Reports\AccountStatementService;
use App\Services\Ledger\Reports\TrialBalanceService;
use App\Services\Ledger\ReversalService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
    config(['app.report_secret' => 'testing-secret']);

    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);
    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Expenses', 'class_id' => $dr->id])->id, 'name' => 'Farm Expenses']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $control->id, 'subcategory_id' => $sub, 'type_id' => $type->id, 'is_settlement' => $settlement,
    ]);

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $sales = $account('Sales A/C', $incomeSub->id);
    $feed = $account('Feed A/C', $expenseSub->id);

    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $template = fn(string $slug, string $kind, int $debit, int $credit, string $side, bool $unit) => TransactionTemplate::create([
        'name' => $slug, 'slug' => $slug, 'transaction_type' => $kind,
        'debit_account_id' => $debit, 'credit_account_id' => $credit, 'settlement_side' => $side, 'requires_farm_unit' => $unit,
    ]);

    $this->sale = $template('sale', 'INCOME', $this->cash->id, $sales->id, 'debit', true);
    $this->spend = $template('spend', 'EXPENSE', $feed->id, $this->cash->id, 'credit', true);
    $template('correction', 'ADJUSTMENT', $this->cash->id, $sales->id, 'none', false);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->clerk = User::factory()->create();
    $this->approver = User::factory()->create();

    $this->region = Region::create(['name' => 'Ashanti']);
    $district = District::create(['name' => 'Kumasi', 'region_id' => $this->region->id]);
    $community = Community::create(['name' => 'Bantama', 'district_id' => $district->id]);

    $this->profile = FarmerProfile::factory()->create(['community_id' => $community->id]);

    // an unapproved unit, so every record on it is provisional
    $this->unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $community->id]);
    FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 100]);

    $this->put = fn(TransactionTemplate $template, string $amount) => app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $template->id,
        amount: $amount,
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        farmUnitId: $this->unit->id,
        recordedBy: $this->clerk->id,
    ));

    $this->cancel = function (Transaction $transaction) {
        $service = app(ReversalService::class);
        $service->approve($service->request($transaction, $this->clerk, 'Typed wrongly'), $this->approver);
    };

    $this->from = now()->subDays(6)->toDateString();
    $this->to = now()->toDateString();
});

test('admin region detail: a cancelled sale and a cancelled expense count as zero per farmer', function () {
    ($this->put)($this->sale, '1000');
    ($this->cancel)(($this->put)($this->sale, '250'));
    ($this->put)($this->spend, '400');
    ($this->cancel)(($this->put)($this->spend, '150'));

    $row = collect($this->actingAs($this->admin)
        ->get("/admin/regions/{$this->region->id}/detail?from={$this->from}&to={$this->to}")
        ->assertOk()->json('farmers'))->firstWhere('id', $this->profile->uuid);

    expect([$row['income'], $row['expense']])->toBe([100000, 40000]);
});

test('statement held back: a cancelled provisional sale holds nothing back, a live one still does (was 50000 for the cancelled one)', function () {
    ($this->put)($this->sale, '100');
    ($this->cancel)(($this->put)($this->sale, '250'));

    $statement = app(AccountStatementService::class)->for($this->profile->id, $this->from, $this->to);

    expect($statement->provisionalHeldBackMinor)->toBe(10000);
});

test('trial balance held back: a cancelled provisional sale holds nothing back, a live one still does (was 50000 for the cancelled one)', function () {
    ($this->put)($this->sale, '100');
    ($this->cancel)(($this->put)($this->sale, '250'));

    $trial = app(TrialBalanceService::class)->for($this->profile->id, $this->from, $this->to);

    expect($trial->provisionalHeldBackMinor)->toBe(10000);
});

test('approval queue: a cancelled provisional record is not counted on its farm unit (was 2 for one cancelled record)', function () {
    ($this->put)($this->sale, '100');
    ($this->cancel)(($this->put)($this->sale, '250'));

    $props = null;

    $this->actingAs($this->admin)->get('/admin/approvals')->assertOk()->assertInertia(function ($page) use (&$props) {
        $props = $page->toArray()['props'];
    });

    $unitItem = collect($props['items']['data'])->first(fn($item) => isset($item['details']['provisional_records']));

    expect($unitItem['details']['provisional_records'])->toBe(1);
});
