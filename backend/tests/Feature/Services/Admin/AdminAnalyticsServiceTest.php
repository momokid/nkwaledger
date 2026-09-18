<?php

use App\Enums\DiseaseReportStatus;
use App\Models\AccountingPeriod;
use App\Models\Community;
use App\Models\District;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\Region;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Admin\AdminAnalyticsService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    config(['app.report_secret' => 'testing-secret']);

    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);
    $expenses = LedgerCategory::create(['name' => 'Expenses', 'class_id' => $drClass->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => $expenses->id, 'name' => 'Farm Expenses']);

    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $subcategoryId, bool $isSettlement = false) => LedgerAccount::create([
        'name' => $name,
        'control_id' => $control->id,
        'subcategory_id' => $subcategoryId,
        'type_id' => $type->id,
        'is_settlement' => $isSettlement,
    ]);

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $this->sales = $account('Sales A/C', $incomeSub->id);
    $this->feed = $account('Feed A/C', $expenseSub->id);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->incomeTemplate = TransactionTemplate::create([
        'name' => 'Crop Sale',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    $this->expenseTemplate = TransactionTemplate::create([
        'name' => 'Feed Purchase',
        'slug' => 'feed_purchase',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $this->feed->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
    ]);

    $this->agentA = User::factory()->create();
    $this->agentA->assignRole('agent');

    $this->agentB = User::factory()->create();
    $this->agentB->assignRole('agent');

    $posting = app(PostingService::class);

    $this->recordIncome = function (FarmerProfile $farmer, string $amount, string $date) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $this->incomeTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->agentA->id,
        ));
    };

    $this->recordExpense = function (FarmerProfile $farmer, string $amount, string $date) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $this->expenseTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->agentA->id,
        ));
    };

    $this->service = app(AdminAnalyticsService::class);
    $this->periodFrom = now()->subDays(29)->toDateString();
    $this->periodTo = now()->toDateString();
});

// --- platformSnapshot ---

test('active_farmers counts distinct farmers with a transaction in the last 30 days, regardless of the filter range', function () {
    $active = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]); // never active

    ($this->recordIncome)($active, '500', now()->toDateString());

    // a filter range from months ago must not change the fixed 30-day activity window
    $snapshot = $this->service->platformSnapshot(now()->subMonths(6)->toDateString(), now()->subMonths(5)->toDateString());

    expect($snapshot['active_farmers'])->toBe(1);
});

test('a transaction older than 30 days does not count a farmer as active', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);

    ($this->recordIncome)($farmer, '500', now()->subDays(45)->toDateString());

    $snapshot = $this->service->platformSnapshot($this->periodFrom, $this->periodTo);

    expect($snapshot['active_farmers'])->toBe(0);
});

test('active_agents counts agents with at least one active farmer', function () {
    $activeFarmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentB->id]); // agent B's farmer stays dormant

    ($this->recordIncome)($activeFarmer, '500', now()->toDateString());

    $snapshot = $this->service->platformSnapshot($this->periodFrom, $this->periodTo);

    expect($snapshot['active_agents'])->toBe(1);
});

test('total_farmers and total_agents are all-time counts, unaffected by activity or the filter', function () {
    FarmerProfile::factory()->count(3)->create(['assigned_agent_id' => $this->agentA->id]);

    $snapshot = $this->service->platformSnapshot(now()->subYears(5)->toDateString(), now()->subYears(4)->toDateString());

    expect($snapshot['total_farmers'])->toBe(3);
    expect($snapshot['total_agents'])->toBe(2);
});

test('total_income, total_expense and net reflect the given period, platform-wide across all farmers', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentB->id]);

    ($this->recordIncome)($farmerA, '500', now()->toDateString());
    ($this->recordIncome)($farmerB, '300', now()->toDateString());
    ($this->recordExpense)($farmerA, '200', now()->toDateString());

    $snapshot = $this->service->platformSnapshot($this->periodFrom, $this->periodTo);

    expect($snapshot['total_income'])->toBe(80000);
    expect($snapshot['total_expense'])->toBe(20000);
    expect($snapshot['net'])->toBe(60000);
});

test('a transaction outside the filtered period is excluded from the platform totals', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);

    ($this->recordIncome)($farmer, '500', now()->subDays(200)->toDateString());

    $snapshot = $this->service->platformSnapshot($this->periodFrom, $this->periodTo);

    expect($snapshot['total_income'])->toBe(0);
});

// --- agentLeaderboard ---

test('farmer_count is the farmers currently assigned to each agent', function () {
    FarmerProfile::factory()->count(2)->create(['assigned_agent_id' => $this->agentA->id]);
    FarmerProfile::factory()->count(1)->create(['assigned_agent_id' => $this->agentB->id]);

    $rows = collect($this->service->agentLeaderboard($this->periodFrom, $this->periodTo));

    expect($rows->firstWhere('agent_id', $this->agentA->id)['farmer_count'])->toBe(2);
    expect($rows->firstWhere('agent_id', $this->agentB->id)['farmer_count'])->toBe(1);
});

test('new_farmers counts only farmers onboarded within the period', function () {
    FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentA->id,
        'onboarded_at' => now(),
    ]);
    FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentA->id,
        'onboarded_at' => now()->subDays(60),
    ]);
    FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentA->id,
        'onboarded_at' => null,
    ]);

    $rows = collect($this->service->agentLeaderboard($this->periodFrom, $this->periodTo));

    expect($rows->firstWhere('agent_id', $this->agentA->id)['new_farmers'])->toBe(1);
});

test('records_logged counts transactions for the agent\'s farmers within the period, not outside it', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);

    ($this->recordIncome)($farmer, '500', now()->toDateString());
    ($this->recordExpense)($farmer, '100', now()->toDateString());
    ($this->recordIncome)($farmer, '999', now()->subDays(200)->toDateString());

    $rows = collect($this->service->agentLeaderboard($this->periodFrom, $this->periodTo));

    expect($rows->firstWhere('agent_id', $this->agentA->id)['records_logged'])->toBe(2);
});

test('income, expense and net reflect each agent\'s own farmers\' totals for the period', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentB->id]);

    ($this->recordIncome)($farmerA, '500', now()->toDateString());
    ($this->recordExpense)($farmerA, '200', now()->toDateString());
    ($this->recordIncome)($farmerB, '300', now()->toDateString());

    $rows = collect($this->service->agentLeaderboard($this->periodFrom, $this->periodTo));

    $rowA = $rows->firstWhere('agent_id', $this->agentA->id);
    expect($rowA['income'])->toBe(50000);
    expect($rowA['expense'])->toBe(20000);
    expect($rowA['net'])->toBe(30000);

    $rowB = $rows->firstWhere('agent_id', $this->agentB->id);
    expect($rowB['income'])->toBe(30000);
    expect($rowB['net'])->toBe(30000);
});

test('every agent appears exactly once, whatever their number of farmers', function () {
    FarmerProfile::factory()->count(3)->create(['assigned_agent_id' => $this->agentA->id]);

    $rows = collect($this->service->agentLeaderboard($this->periodFrom, $this->periodTo));

    expect($rows->where('agent_id', $this->agentA->id)->count())->toBe(1);
    expect($rows->count())->toBe(2);
});

test('an agent with no farmers still appears, with all-zero figures', function () {
    $rows = collect($this->service->agentLeaderboard($this->periodFrom, $this->periodTo));

    $row = $rows->firstWhere('agent_id', $this->agentB->id);

    expect($row['farmer_count'])->toBe(0);
    expect($row['new_farmers'])->toBe(0);
    expect($row['records_logged'])->toBe(0);
    expect($row['income'])->toBe(0);
    expect($row['net'])->toBe(0);
});

test('the leaderboard is sorted by net, highest first, by default', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentB->id]);

    ($this->recordIncome)($farmerA, '100', now()->toDateString());
    ($this->recordIncome)($farmerB, '900', now()->toDateString());

    $rows = $this->service->agentLeaderboard($this->periodFrom, $this->periodTo);

    expect($rows[0]['agent_id'])->toBe($this->agentB->id);
    expect($rows[0]['rank'])->toBe(1);
    expect($rows[1]['agent_id'])->toBe($this->agentA->id);
    expect($rows[1]['rank'])->toBe(2);
});

test('the leaderboard can be sorted by records_logged (activity) instead', function () {
    $farmerA = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id]);
    $farmerB = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentB->id]);

    // agent A has the higher net, but agent B logged more individual records
    ($this->recordIncome)($farmerA, '900', now()->toDateString());
    ($this->recordIncome)($farmerB, '10', now()->toDateString());
    ($this->recordIncome)($farmerB, '10', now()->subDay()->toDateString());
    ($this->recordExpense)($farmerB, '5', now()->toDateString());

    $rows = $this->service->agentLeaderboard($this->periodFrom, $this->periodTo, sort: 'activity');

    expect($rows[0]['agent_id'])->toBe($this->agentB->id);
    expect($rows[0]['records_logged'])->toBe(3);
});

test('every row exposes both net and records_logged regardless of which sort is active', function () {
    $rows = collect($this->service->agentLeaderboard($this->periodFrom, $this->periodTo, sort: 'activity'));

    expect($rows->first())->toHaveKeys(['net', 'records_logged']);
});

test('an unrecognised sort value falls back to net', function () {
    $rows = $this->service->agentLeaderboard($this->periodFrom, $this->periodTo, sort: 'nonsense');
    $default = $this->service->agentLeaderboard($this->periodFrom, $this->periodTo);

    expect($rows)->toBe($default);
});

// --- regionalTrends ---

test('farmer_count and farm_unit_count are correct per region', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Kalpohin', 'district_id' => $district->id]);

    $farmer = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentA->id,
        'community_id' => $community->id,
    ]);
    \App\Models\FarmUnit::factory()->count(2)->create(['farmer_profile_id' => $farmer->id]);

    $rows = collect($this->service->regionalTrends($this->periodFrom, $this->periodTo));
    $row = $rows->firstWhere('region_id', $region->id);

    expect($row['farmer_count'])->toBe(1);
    expect($row['farm_unit_count'])->toBe(2);
});

test('income, expense and net are correct per region for the given period', function () {
    $region = Region::create(['name' => 'Ashanti']);
    $district = District::create(['name' => 'Kumasi', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Bantama', 'district_id' => $district->id]);

    $farmer = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentA->id,
        'community_id' => $community->id,
    ]);

    ($this->recordIncome)($farmer, '500', now()->toDateString());
    ($this->recordExpense)($farmer, '200', now()->toDateString());

    $rows = collect($this->service->regionalTrends($this->periodFrom, $this->periodTo));
    $row = $rows->firstWhere('region_id', $region->id);

    expect($row['income'])->toBe(50000);
    expect($row['expense'])->toBe(20000);
    expect($row['net'])->toBe(30000);
});

test('a transaction outside the filtered period is excluded from a region\'s totals', function () {
    $region = Region::create(['name' => 'Volta']);
    $district = District::create(['name' => 'Ho', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Bankoe', 'district_id' => $district->id]);

    $farmer = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentA->id,
        'community_id' => $community->id,
    ]);

    ($this->recordIncome)($farmer, '500', now()->subDays(200)->toDateString());

    $rows = collect($this->service->regionalTrends($this->periodFrom, $this->periodTo));
    $row = $rows->firstWhere('region_id', $region->id);

    expect($row['income'])->toBe(0);
});

test('each region appears exactly once, even with multiple farmers and communities in it', function () {
    $region = Region::create(['name' => 'Bono']);
    $districtOne = District::create(['name' => 'Sunyani', 'region_id' => $region->id]);
    $districtTwo = District::create(['name' => 'Berekum', 'region_id' => $region->id]);
    $communityOne = Community::create(['name' => 'Abesim', 'district_id' => $districtOne->id]);
    $communityTwo = Community::create(['name' => 'Jamdede', 'district_id' => $districtTwo->id]);

    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id, 'community_id' => $communityOne->id]);
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentA->id, 'community_id' => $communityTwo->id]);

    $rows = collect($this->service->regionalTrends($this->periodFrom, $this->periodTo));

    expect($rows->where('region_id', $region->id)->count())->toBe(1);
    expect($rows->firstWhere('region_id', $region->id)['farmer_count'])->toBe(2);
});

// --- healthTrends ---

test('total, by_category and by_status counts are correct per region', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Kalpohin', 'district_id' => $district->id]);
    $farmer = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentA->id,
        'community_id' => $community->id,
    ]);
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $farmer->id]);

    DiseaseReport::factory()->create([
        'farmer_profile_id' => $farmer->id,
        'farm_unit_id' => $unit->id,
        'category' => 'Livestock',
        'status' => DiseaseReportStatus::New,
    ]);
    DiseaseReport::factory()->create([
        'farmer_profile_id' => $farmer->id,
        'farm_unit_id' => $unit->id,
        'category' => 'Crop',
        'status' => DiseaseReportStatus::Resolved,
    ]);

    $rows = collect($this->service->healthTrends($this->periodFrom, $this->periodTo));
    $row = $rows->firstWhere('region_id', $region->id);

    expect($row['total'])->toBe(2);
    expect($row['by_category']['Livestock'])->toBe(1);
    expect($row['by_category']['Crop'])->toBe(1);
    expect($row['by_status']['new'])->toBe(1);
    expect($row['by_status']['resolved'])->toBe(1);
});

test('a report outside the filtered period is excluded from a region\'s health totals', function () {
    $region = Region::create(['name' => 'Ashanti']);
    $district = District::create(['name' => 'Kumasi', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Bantama', 'district_id' => $district->id]);
    $farmer = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentA->id,
        'community_id' => $community->id,
    ]);
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $farmer->id]);

    DiseaseReport::factory()->create([
        'farmer_profile_id' => $farmer->id,
        'farm_unit_id' => $unit->id,
        'created_at' => now()->subDays(200),
    ]);

    $rows = collect($this->service->healthTrends($this->periodFrom, $this->periodTo));
    $row = $rows->firstWhere('region_id', $region->id);

    expect($row['total'] ?? 0)->toBe(0);
});

test('each region appears exactly once in health trends, even with multiple reports', function () {
    $region = Region::create(['name' => 'Volta']);
    $district = District::create(['name' => 'Ho', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Bankoe', 'district_id' => $district->id]);
    $farmer = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentA->id,
        'community_id' => $community->id,
    ]);
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $farmer->id]);

    DiseaseReport::factory()->count(3)->create([
        'farmer_profile_id' => $farmer->id,
        'farm_unit_id' => $unit->id,
    ]);

    $rows = collect($this->service->healthTrends($this->periodFrom, $this->periodTo));

    expect($rows->where('region_id', $region->id)->count())->toBe(1);
    expect($rows->firstWhere('region_id', $region->id)['total'])->toBe(3);
});
