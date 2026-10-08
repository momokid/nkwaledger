<?php

// a cancelled record counts as zero in every figure that adds up transactions, as the statement already shows.
// Each test posts a record that stays and one that is cancelled, then compares the figures.
// The agent activity feed is the exception: it still lists a cancelled record, marked as cancelled.

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
use App\Services\Admin\AdminAnalyticsService;
use App\Services\Agent\AgentActivityFeedService;
use App\Services\Agent\AgentIncomeSummaryService;
use App\Services\Agent\FarmerRosterService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\ReversalService;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 6.7, 'longitude' => -1.5]]]),
        'api.open-meteo.com/*' => Http::response(['daily' => [
            'precipitation_sum' => [2, 5, 3],
            'temperature_2m_max' => [28, 29, 27],
            'windspeed_10m_max' => [15, 18, 12],
        ]]),
    ]);

    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id]);
    $expenses = LedgerCategory::create(['name' => 'Expenses', 'class_id' => $dr->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => $expenses->id, 'name' => 'Farm Expenses']);

    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name,
        'control_id' => $control->id,
        'subcategory_id' => $sub,
        'type_id' => $type->id,
        'is_settlement' => $settlement,
    ]);

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $account('Accounts Receivable', $assetSub->id, true);
    $account('Accounts Payable', $assetSub->id, true);
    $this->sales = $account('Sales A/C', $incomeSub->id);
    $this->feed = $account('Feed A/C', $expenseSub->id);
    $this->lossAccount = $account('Loss A/C', $expenseSub->id);
    $this->livestock = $account('Livestock A/C', $assetSub->id);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $template = fn(string $slug, string $type, int $dr, int $cr, string $side, bool $unit = false) => TransactionTemplate::create([
        'name' => $slug,
        'slug' => $slug,
        'transaction_type' => $type,
        'debit_account_id' => $dr,
        'credit_account_id' => $cr,
        'settlement_side' => $side,
        'requires_farm_unit' => $unit,
    ]);

    $this->sale = $template('plain_sale', 'INCOME', $this->cash->id, $this->sales->id, 'debit');
    $this->spend = $template('plain_spend', 'EXPENSE', $this->feed->id, $this->cash->id, 'credit');
    $this->unitSale = $template('unit_sale', 'INCOME', $this->cash->id, $this->sales->id, 'debit', true);
    $this->unitSpend = $template('unit_spend', 'EXPENSE', $this->feed->id, $this->cash->id, 'credit', true);
    $this->loss = $template('unit_loss', 'LOSS', $this->lossAccount->id, $this->livestock->id, 'none', true);
    $template('correction', 'ADJUSTMENT', $this->cash->id, $this->sales->id, 'none');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->clerk = User::factory()->create();
    $this->approver = User::factory()->create();

    $region = Region::create(['name' => 'Ashanti']);
    $district = District::create(['name' => 'Kumasi', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Bantama', 'district_id' => $district->id]);
    $this->region = $region;

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create([
        'user_id' => $this->farmerUser->id,
        'assigned_agent_id' => $this->agent->id,
        'community_id' => $community->id,
    ]);

    $this->unit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'community_id' => $community->id,
    ]);
    FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 1000]);

    $posting = app(PostingService::class);

    $this->put = function (TransactionTemplate $template, string $amount) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $this->profile->id,
            transactionTemplateId: $template->id,
            amount: $amount,
            settlementAccountId: $template->settlement_side === 'none' ? null : $this->cash->id,
            transactionDate: now()->toDateString(),
            farmUnitId: $template->requires_farm_unit ? $this->unit->id : null,
            recordedBy: $this->clerk->id,
            quantityLost: $template->transaction_type === 'LOSS' ? '1' : null,
        ));
    };

    $this->cancel = function (Transaction $transaction) {
        $service = app(ReversalService::class);
        $request = $service->request($transaction, $this->clerk, 'Typed wrongly');
        $service->approve($request, $this->approver);
    };

    // one record stays, one is cancelled
    $this->keepAndCancel = function (TransactionTemplate $template, string $kept, string $cancelled) {
        ($this->put)($template, $kept);
        ($this->cancel)(($this->put)($template, $cancelled));
    };

    // amount => is_cancelled, for the transaction entries of the feed
    $this->feedOf = fn() => collect(app(AgentActivityFeedService::class)->recentFor($this->agent->id, $this->from, $this->to, 10))
        ->where('kind', 'transaction')
        ->mapWithKeys(fn($entry) => [$entry['amount_minor'] => $entry['is_cancelled']])
        ->sortKeysDesc()
        ->all();

    $this->from = now()->subDays(6)->toDateString();
    $this->to = now()->toDateString();

    $this->dashboard = function () {
        $props = null;

        $this->actingAs($this->farmerUser)->get('/farmer/dashboard')
            ->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props;
    };

    $this->myFarm = function () {
        $props = null;

        $this->actingAs($this->farmerUser)->get('/my-farm')
            ->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props['units'][0]['analysis'];
    };
});

// --- IncomeAndExpenditureService ---

test('income and expenditure: a cancelled sale counts as zero', function () {
    ($this->keepAndCancel)($this->sale, '1000', '250');

    $report = app(IncomeAndExpenditureService::class)->for($this->profile->id, $this->from, $this->to);

    expect([
        'income' => $report->totalIncomeMinor,
        'net' => $report->netMinor,
        'cash_collected' => $report->cashCollectedMinor,
    ])->toBe(['income' => 100000, 'net' => 100000, 'cash_collected' => 100000]);
});

test('income and expenditure: a cancelled expense counts as zero', function () {
    ($this->keepAndCancel)($this->spend, '400', '150');

    $report = app(IncomeAndExpenditureService::class)->for($this->profile->id, $this->from, $this->to);

    expect([
        'expense' => $report->totalExpenseMinor,
        'net' => $report->netMinor,
        'cash_paid_out' => $report->cashPaidOutMinor,
    ])->toBe(['expense' => 40000, 'net' => -40000, 'cash_paid_out' => 40000]);
});

test('income and expenditure: a cancelled loss counts as zero', function () {
    ($this->keepAndCancel)($this->loss, '300', '120');

    $report = app(IncomeAndExpenditureService::class)->for($this->profile->id, $this->from, $this->to);

    expect(['loss' => $report->totalLossMinor, 'net' => $report->netMinor])
        ->toBe(['loss' => 30000, 'net' => -30000]);
});

// --- Farmer dashboard ---

test('farmer dashboard: a cancelled sale counts as zero', function () {
    ($this->keepAndCancel)($this->sale, '1000', '250');

    $summary = ($this->dashboard)()['summary'];

    expect([
        'income' => $summary['total_income'],
        'net' => $summary['net'],
        'cash_collected' => $summary['cash_collected'],
    ])->toBe(['income' => 100000, 'net' => 100000, 'cash_collected' => 100000]);
});

test('farmer dashboard: a cancelled expense counts as zero', function () {
    ($this->keepAndCancel)($this->spend, '400', '150');

    $summary = ($this->dashboard)()['summary'];

    expect([
        'expense' => $summary['total_expense'],
        'net' => $summary['net'],
        'cash_paid_out' => $summary['cash_paid_out'],
    ])->toBe(['expense' => 40000, 'net' => -40000, 'cash_paid_out' => 40000]);
});

test('farmer dashboard: a cancelled loss counts as zero', function () {
    ($this->keepAndCancel)($this->loss, '300', '120');

    $lossRows = ($this->dashboard)()['breakdown']['loss_rows'];

    expect(collect($lossRows)->sum('amount'))->toBe(30000);
});

// --- My Farm analysis ---

test('my farm: a cancelled sale counts as zero', function () {
    ($this->keepAndCancel)($this->unitSale, '1000', '250');

    $analysis = ($this->myFarm)();

    expect(['income' => $analysis['total_income'], 'net' => $analysis['net']])
        ->toBe(['income' => 100000, 'net' => 100000]);
});

test('my farm: a cancelled expense counts as zero', function () {
    ($this->keepAndCancel)($this->unitSpend, '400', '150');

    $analysis = ($this->myFarm)();

    expect(['expense' => $analysis['total_expense'], 'net' => $analysis['net']])
        ->toBe(['expense' => 40000, 'net' => -40000]);
});

test('my farm: a cancelled loss counts as zero', function () {
    ($this->keepAndCancel)($this->loss, '300', '120');

    expect(($this->myFarm)()['total_loss'])->toBe(30000);
});

// --- AdminAnalyticsService ---

test('admin regional trends: a cancelled sale counts as zero', function () {
    ($this->keepAndCancel)($this->sale, '1000', '250');

    $row = collect(app(AdminAnalyticsService::class)->regionalTrends($this->from, $this->to))
        ->firstWhere('region_id', $this->region->id);

    expect(['income' => $row['income'], 'net' => $row['net']])->toBe(['income' => 100000, 'net' => 100000]);
});

test('admin regional trends: a cancelled expense counts as zero', function () {
    ($this->keepAndCancel)($this->spend, '400', '150');

    $row = collect(app(AdminAnalyticsService::class)->regionalTrends($this->from, $this->to))
        ->firstWhere('region_id', $this->region->id);

    expect(['expense' => $row['expense'], 'net' => $row['net']])->toBe(['expense' => 40000, 'net' => -40000]);
});

test('admin platform snapshot: a cancelled sale counts as zero', function () {
    ($this->keepAndCancel)($this->sale, '1000', '250');

    $snapshot = app(AdminAnalyticsService::class)->platformSnapshot($this->from, $this->to);

    expect(['income' => $snapshot['total_income'], 'net' => $snapshot['net']])
        ->toBe(['income' => 100000, 'net' => 100000]);
});

test('admin platform snapshot: a cancelled expense counts as zero', function () {
    ($this->keepAndCancel)($this->spend, '400', '150');

    $snapshot = app(AdminAnalyticsService::class)->platformSnapshot($this->from, $this->to);

    expect(['expense' => $snapshot['total_expense'], 'net' => $snapshot['net']])
        ->toBe(['expense' => 40000, 'net' => -40000]);
});

test('admin agent leaderboard: a cancelled sale and expense count as zero', function () {
    ($this->keepAndCancel)($this->sale, '1000', '250');
    ($this->keepAndCancel)($this->spend, '400', '150');

    $row = collect(app(AdminAnalyticsService::class)->agentLeaderboard($this->from, $this->to))
        ->firstWhere('agent_id', $this->agent->id);

    expect(['income' => $row['income'], 'expense' => $row['expense']])
        ->toBe(['income' => 100000, 'expense' => 40000]);
});

// --- Agent services ---

test('roster weekly sum: a cancelled sale counts as zero', function () {
    ($this->keepAndCancel)($this->sale, '1000', '250');

    $buckets = app(FarmerRosterService::class)->weeklyTotalsFor($this->agent->id, $this->from, $this->to);

    expect(end($buckets)['income'])->toBe(100000);
});

test('roster weekly sum: a cancelled expense counts as zero', function () {
    ($this->keepAndCancel)($this->spend, '400', '150');

    $buckets = app(FarmerRosterService::class)->weeklyTotalsFor($this->agent->id, $this->from, $this->to);

    expect(end($buckets)['expense'])->toBe(40000);
});

test('roster totals: a cancelled sale and expense count as zero', function () {
    ($this->keepAndCancel)($this->sale, '1000', '250');
    ($this->keepAndCancel)($this->spend, '400', '150');

    [$income, $expense, , , $collected, $paidOut] = app(FarmerRosterService::class)
        ->totalsFor($this->agent->id, $this->from, $this->to);

    expect(compact('income', 'expense', 'collected', 'paidOut'))
        ->toBe(['income' => 100000, 'expense' => 40000, 'collected' => 100000, 'paidOut' => 40000]);
});

test('agent income summary: a cancelled sale and expense count as zero', function () {
    ($this->keepAndCancel)($this->sale, '1000', '250');
    ($this->keepAndCancel)($this->spend, '400', '150');

    $summary = app(AgentIncomeSummaryService::class)->for($this->agent->id, $this->from, $this->to);

    expect([
        'income' => $summary['total_income'],
        'expense' => $summary['total_expense'],
        'net' => $summary['net'],
    ])->toBe(['income' => 100000, 'expense' => 40000, 'net' => 60000]);
});

test('agent activity feed: a cancelled sale is listed and marked cancelled', function () {
    ($this->put)($this->sale, '1000');
    ($this->cancel)(($this->put)($this->sale, '250'));

    expect(($this->feedOf)())->toBe([100000 => false, 25000 => true]);
});

test('agent activity feed: a cancelled expense is listed and marked cancelled', function () {
    ($this->put)($this->sale, '1000');
    ($this->cancel)(($this->put)($this->spend, '150'));

    expect(($this->feedOf)())->toBe([100000 => false, 15000 => true]);
});

test('agent activity feed: a cancelled loss is listed and marked cancelled', function () {
    ($this->put)($this->sale, '1000');
    ($this->cancel)(($this->put)($this->loss, '120'));

    expect(($this->feedOf)())->toBe([100000 => false, 12000 => true]);
});

test('a farmer with only a cancelled sale is not counted as active', function () {
    ($this->cancel)(($this->put)($this->sale, '250'));

    [, , $active] = app(FarmerRosterService::class)->totalsFor($this->agent->id, $this->from, $this->to);

    expect($active)->toBe(0);
});

// controls: the setup itself is sound, and the statement already treats a cancelled record as zero
test('control: with nothing cancelled the report shows exactly what was posted', function () {
    ($this->put)($this->sale, '1000');

    $report = app(IncomeAndExpenditureService::class)->for($this->profile->id, $this->from, $this->to);

    expect($report->totalIncomeMinor)->toBe(100000);
});

test('control: the statement nets a cancelled sale to zero in the cash balance', function () {
    ($this->keepAndCancel)($this->sale, '1000', '250');

    $statement = app(\App\Services\Ledger\Reports\AccountStatementService::class)
        ->for($this->profile->id, $this->from, $this->to);

    expect(['closing' => $statement->closingBalanceMinor, 'cancelled' => $statement->cancelledMinor])
        ->toBe(['closing' => 100000, 'cancelled' => 25000]);
});
