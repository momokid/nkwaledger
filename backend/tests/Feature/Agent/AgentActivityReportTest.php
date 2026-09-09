<?php

use App\Models\AccountingPeriod;
use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
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

    $this->agentUser = User::factory()->create();
    $this->agentUser->assignRole('agent');

    $posting = app(PostingService::class);

    $this->recordIncome = function (FarmerProfile $farmer, string $amount, string $date) use ($posting) {
        return $posting->post(new PostingRequest(
            farmerProfileId: $farmer->id,
            transactionTemplateId: $this->incomeTemplate->id,
            amount: $amount,
            settlementAccountId: $this->cash->id,
            transactionDate: $date,
            recordedBy: $this->agentUser->id,
        ));
    };
});

test('a guest is redirected to login', function () {
    $this->get('/agent/reports/activity')->assertRedirect('/login');
});

test('an agent sees the activity report screen', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/activity')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Agent/Reports/Activity'));
});

test('a user without the view permission is refused', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/agent/reports/activity')->assertForbidden();
});

test('the roster matches what the dashboard would show', function () {
    $community = Community::factory()->create(['name' => 'Ejisu']);
    $farmer = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentUser->id,
        'community_id' => $community->id,
    ]);
    $farmer->user->update(['surname' => 'Mensah', 'first_name' => 'Ama']);

    ($this->recordIncome)($farmer, '500', now()->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/reports/activity')
        ->assertInertia(fn($page) => $page
            ->where('roster.0.name', 'Mensah Ama')
            ->where('roster.0.community', 'Ejisu')
            ->where('roster.0.income', 50000)
            ->where('roster.0.status', 'active'));
});

test('the print view renders for an agent', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/activity/print')
        ->assertOk()
        ->assertSee('Farmer Activity');
});

test('the print view carries the internal-use notice', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/activity/print')
        ->assertSee('Internal use only');
});

test('a guest cannot reach the print view', function () {
    $this->get('/agent/reports/activity/print')->assertRedirect('/login');
});
