<?php

use App\Models\AccountingPeriod;
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
    $this->get('/agent/reports/dormant')->assertRedirect('/login');
});

test('an agent sees the dormant report screen', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/dormant')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Agent/Reports/Dormant'));
});

test('a user without the view permission is refused', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/agent/reports/dormant')->assertForbidden();
});

test('only dormant farmers appear, active farmers are excluded', function () {
    $active = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $active->user->update(['surname' => 'Active', 'first_name' => 'Farmer']);
    ($this->recordIncome)($active, '500', now()->toDateString());

    $dormant = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $dormant->user->update(['surname' => 'Dormant', 'first_name' => 'Farmer']);

    $this->actingAs($this->agentUser)->get('/agent/reports/dormant')
        ->assertInertia(fn($page) => $page
            ->has('roster', 1)
            ->where('roster.0.name', 'Dormant Farmer'));
});

test('a farmer who has never logged anything is listed first', function () {
    $neverActive = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $neverActive->user->update(['surname' => 'Never', 'first_name' => 'Active']);

    $wentQuiet = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agentUser->id]);
    $wentQuiet->user->update(['surname' => 'Went', 'first_name' => 'Quiet']);
    ($this->recordIncome)($wentQuiet, '500', now()->subDays(90)->toDateString());

    $this->actingAs($this->agentUser)->get('/agent/reports/dormant')
        ->assertInertia(fn($page) => $page
            ->where('roster.0.name', 'Never Active')
            ->where('roster.1.name', 'Went Quiet'));
});

test('the print view renders for an agent', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/dormant/print')
        ->assertOk()
        ->assertSee('Dormant Farmers');
});

test('the print view carries the internal-use notice', function () {
    $this->actingAs($this->agentUser)->get('/agent/reports/dormant/print')
        ->assertSee('Internal use only');
});

test('a guest cannot reach the print view', function () {
    $this->get('/agent/reports/dormant/print')->assertRedirect('/login');
});
