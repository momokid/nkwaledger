<?php

use App\Models\AccountingPeriod;
use App\Models\Community;
use App\Models\District;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Region;
use App\Models\TransactionTemplate;
use App\Models\User;
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

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);

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

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->incomeTemplate = TransactionTemplate::create([
        'name' => 'Crop Sale',
        'slug' => 'crop_sale_region_detail_test',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->recordIncome = function (FarmerProfile $farmer, string $amount, string $date) {
        return app(PostingService::class)->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $this->incomeTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->agent->id,
        ));
    };

    $this->from = now()->subDays(29)->toDateString();
    $this->to = now()->toDateString();
});

test('a guest is redirected to login', function () {
    $region = Region::create(['name' => 'Northern']);

    $this->get("/admin/regions/{$region->id}/detail")->assertRedirect('/login');
});

test('a non-admin is forbidden', function () {
    $region = Region::create(['name' => 'Northern']);
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $this->actingAs($farmer)
        ->get("/admin/regions/{$region->id}/detail")
        ->assertForbidden();
});

test('admin sees only farmers in that region, with their income for the period', function () {
    $region = Region::create(['name' => 'Ashanti']);
    $district = District::create(['name' => 'Kumasi', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Bantama', 'district_id' => $district->id]);

    $otherRegion = Region::create(['name' => 'Volta']);
    $otherDistrict = District::create(['name' => 'Ho', 'region_id' => $otherRegion->id]);
    $otherCommunity = Community::create(['name' => 'Bankoe', 'district_id' => $otherDistrict->id]);

    $farmerIn = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agent->id,
        'community_id' => $community->id,
    ]);
    $farmerOut = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agent->id,
        'community_id' => $otherCommunity->id,
    ]);

    ($this->recordIncome)($farmerIn, '500', now()->toDateString());
    ($this->recordIncome)($farmerOut, '999', now()->toDateString());

    $response = $this->actingAs($this->admin)
        ->get("/admin/regions/{$region->id}/detail?from={$this->from}&to={$this->to}")
        ->assertOk()
        ->json();

    expect($response['farmers'])->toHaveCount(1);
    expect($response['farmers'][0]['income'])->toBe(50000);
});
