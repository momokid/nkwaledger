<?php

use App\Models\AccountingPeriod;
use App\Models\Community;
use App\Models\FarmerGroup;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
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

    $this->community = Community::factory()->create(['name' => 'Ejisu']);
    $this->group = FarmerGroup::factory()->create(['name' => 'Ejisu Poultry Group']);
    $this->farmType = FarmType::factory()->withCategory()->create(['name' => 'Poultry']);

    $this->farmer = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agentUser->id,
        'community_id' => $this->community->id,
        'farmer_group_id' => $this->group->id,
        'gender' => 'female',
        'date_of_birth' => '1990-04-12',
        'home_address' => 'House 12, Ejisu',
    ]);
    $this->farmer->user->update(['surname' => 'Mensah', 'first_name' => 'Ama']);
    $this->farmer->farmTypes()->attach($this->farmType->id);

    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->farmer->id,
        'farm_type_id' => $this->farmType->id,
        'community_id' => $this->community->id,
        'name' => 'Pen A',
        'capacity' => 250,
        'capacity_unit' => 'birds',
    ]);

    app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->farmer->id,
        transactionTemplateId: $this->incomeTemplate->id,
        amount: '500',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->agentUser->id,
    ));
});

test('a guest is redirected to login', function () {
    $this->get("/agent/farmers/{$this->farmer->uuid}/profile-report")->assertRedirect('/login');
});

test('an agent can view the profile report for a farmer they hold', function () {
    $this->actingAs($this->agentUser)->get("/agent/farmers/{$this->farmer->uuid}/profile-report")
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Agent/Reports/FarmerProfile'));
});

test('an agent cannot view a farmer held by another agent', function () {
    $otherAgent = User::factory()->create();
    $otherAgent->assignRole('agent');
    $other = FarmerProfile::factory()->create(['assigned_agent_id' => $otherAgent->id]);

    $this->actingAs($this->agentUser)->get("/agent/farmers/{$other->uuid}/profile-report")
        ->assertNotFound();
});

test('a user without the view permission is refused', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get("/agent/farmers/{$this->farmer->uuid}/profile-report")
        ->assertForbidden();
});

test('shows the farmer\'s personal and registration details', function () {
    $this->actingAs($this->agentUser)->get("/agent/farmers/{$this->farmer->uuid}/profile-report")
        ->assertInertia(fn($page) => $page
            ->where('farmer.name', 'Mensah Ama')
            ->where('farmer.gender', 'female')
            ->where('farmer.date_of_birth', '1990-04-12')
            ->where('farmer.home_address', 'House 12, Ejisu')
            ->where('farmer.community', 'Ejisu')
            ->where('farmer.farmer_group', 'Ejisu Poultry Group')
            ->where('farmer.is_active', true));
});

test('shows the identity status without the raw document number', function () {
    $this->farmer->identity_type = App\Enums\IdentityType::GhanaCard;
    $this->farmer->identity_number = 'GHA-123456789-0';
    $this->farmer->save();

    $response = $this->actingAs($this->agentUser)->get("/agent/farmers/{$this->farmer->uuid}/profile-report");

    $response->assertInertia(fn($page) => $page
        ->where('farmer.identity.has_document', true)
        ->where('farmer.identity.verified', false));

    $response->assertDontSee('123456789');
});

test('lists the farmer\'s farm types', function () {
    $this->actingAs($this->agentUser)->get("/agent/farmers/{$this->farmer->uuid}/profile-report")
        ->assertInertia(fn($page) => $page->where('farmer.farm_types', ['Poultry']));
});

test('lists the farmer\'s farm units', function () {
    $this->actingAs($this->agentUser)->get("/agent/farmers/{$this->farmer->uuid}/profile-report")
        ->assertInertia(fn($page) => $page
            ->where('farm_units.0.name', 'Pen A')
            ->where('farm_units.0.farm_type', 'Poultry')
            ->where('farm_units.0.capacity', '250.00')
            ->where('farm_units.0.capacity_unit', 'birds')
            ->where('farm_units.0.is_approved', true));
});

test('shows the financial summary for the period', function () {
    $this->actingAs($this->agentUser)->get("/agent/farmers/{$this->farmer->uuid}/profile-report")
        ->assertInertia(fn($page) => $page
            ->where('summary.total_income', 50000)
            ->where('summary.net', 50000));
});

test('shows credit score as not yet available', function () {
    $this->actingAs($this->agentUser)->get("/agent/farmers/{$this->farmer->uuid}/profile-report")
        ->assertInertia(fn($page) => $page->where('credit_score', 'Not yet available'));
});

test('the print view renders for an agent', function () {
    $this->actingAs($this->agentUser)->get("/agent/farmers/{$this->farmer->uuid}/profile-report/print")
        ->assertOk()
        ->assertSee('Farmer Profile')
        ->assertSee('Mensah Ama')
        ->assertSee('Not yet available');
});

test('the print view never shows the raw identity number', function () {
    $this->farmer->identity_type = App\Enums\IdentityType::GhanaCard;
    $this->farmer->identity_number = 'GHA-123456789-0';
    $this->farmer->save();

    $this->actingAs($this->agentUser)->get("/agent/farmers/{$this->farmer->uuid}/profile-report/print")
        ->assertDontSee('123456789');
});

test('the print view carries the internal-use notice', function () {
    $this->actingAs($this->agentUser)->get("/agent/farmers/{$this->farmer->uuid}/profile-report/print")
        ->assertSee('Internal use only');
});

test('a guest cannot reach the print view', function () {
    $this->get("/agent/farmers/{$this->farmer->uuid}/profile-report/print")->assertRedirect('/login');
});
