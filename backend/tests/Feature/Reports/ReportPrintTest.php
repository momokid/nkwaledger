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

    $this->cash = LedgerAccount::create([
        'name' => 'Cash A/C',
        'control_id' => $control->id,
        'subcategory_id' => $assetSub->id,
        'type_id' => $type->id,
        'is_settlement' => true,
    ]);

    $this->sales = LedgerAccount::create([
        'name' => 'Income on Sales',
        'control_id' => $control->id,
        'subcategory_id' => $incomeSub->id,
        'type_id' => $type->id,
    ]);

    $this->saleTemplate = TransactionTemplate::create([
        'name' => 'I sold my farm produce',
        'slug' => 'produce_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
    ]);

    AccountingPeriod::create([
        'name' => 'This Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');

    $this->profile = FarmerProfile::factory()->create([
        'user_id' => $this->farmerUser->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $this->record = app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->saleTemplate->id,
        amount: '250',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        narration: 'Sold maize',
        recordedBy: $this->farmerUser->id,
    ));
});

// a page a bank can hold, with nothing from the app around it
it('prints a farmer their own report', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports/print')
        ->assertOk()
        ->assertSee('NkwaLedger');
});

it('turns a stranger away', function () {
    $this->actingAs(User::factory()->create())
        ->get('/my-reports/print')
        ->assertForbidden();
});

it('carries no sidebar or menu', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports/print')
        ->assertDontSee('Dashboard')
        ->assertDontSee('My Money');
});

it('names the farmer and the period', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports/print')
        ->assertSee($this->profile->user->surname)
        ->assertSee($this->profile->uuid);
});

it('shows the check code', function () {
    $response = $this->actingAs($this->farmerUser)->get('/my-reports/print');

    $response->assertOk();

    expect($response->getContent())->toMatch('/[A-Z0-9]{12}/');
});

it('lists the records', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports/print')
        ->assertSee($this->record->reference)
        ->assertSee('Sold maize');
});

it('shows amounts in cedis, not pesewas', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports/print')
        ->assertSee('250.00')
        ->assertDontSee('25000');
});

it('prints income and expenditure when asked', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports/print?kind=income')
        ->assertOk()
        ->assertSee('Income and Expenditure');
});

it('prints a trial balance for staff', function () {
    $this->actingAs($this->admin)
        ->get("/admin/farmers/{$this->profile->uuid}/reports/print?kind=trial-balance")
        ->assertOk()
        ->assertSee('Trial Balance');
});

// the one report a farmer should never be handed
it('refuses a farmer a trial balance', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports/print?kind=trial-balance')
        ->assertOk()
        ->assertSee('Account Statement');
});

it('reads the dates it was asked for', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports/print?from=' . now()->addYear()->startOfYear()->toDateString()
            . '&to=' . now()->addYear()->endOfYear()->toDateString())
        ->assertDontSee($this->record->reference);
});

it('says whether records waiting on approval are in or out', function () {
    $this->actingAs($this->admin)
        ->get("/admin/farmers/{$this->profile->uuid}/reports/print")
        ->assertSee('Left out');
});

it('prints for an agent on a farmer they hold', function () {
    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$this->profile->uuid}/reports/print")
        ->assertOk();
});

it('hides a farmer the agent does not hold', function () {
    $other = FarmerProfile::factory()->create();

    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$other->uuid}/reports/print")
        ->assertNotFound();
});

it('says nothing was recorded when the period is empty', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-reports/print?from=' . now()->addYear()->startOfYear()->toDateString()
            . '&to=' . now()->addYear()->endOfYear()->toDateString())
        ->assertSee('Nothing was recorded');
});
