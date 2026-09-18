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

// --- PDF ---

it('downloads a pdf with the right content type', function () {
    $response = $this->actingAs($this->farmerUser)->get('/my-reports/pdf');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

it('produces a real pdf file, not an error page', function () {
    $response = $this->actingAs($this->farmerUser)->get('/my-reports/pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

it('the pdf contains the farmer, the reference, and the amount', function () {
    $response = $this->actingAs($this->farmerUser)->get('/my-reports/pdf');

    $content = $response->getContent();

    expect($content)->toContain($this->profile->uuid);
    expect($content)->toContain($this->record->reference);
    expect($content)->toContain('250.00');
});

it('the pdf respects the date range filter', function () {
    $response = $this->actingAs($this->farmerUser)->get(
        '/my-reports/pdf?from=' . now()->addYear()->startOfYear()->toDateString()
            . '&to=' . now()->addYear()->endOfYear()->toDateString(),
    );

    expect($response->getContent())->not->toContain($this->record->reference);
});

it('an agent can download the pdf for a farmer they hold', function () {
    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$this->profile->uuid}/reports/pdf")
        ->assertOk();
});

it('an agent cannot download the pdf for a farmer they do not hold', function () {
    $other = FarmerProfile::factory()->create();

    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$other->uuid}/reports/pdf")
        ->assertNotFound();
});

it('an admin can download the pdf for any farmer', function () {
    $this->actingAs($this->admin)
        ->get("/admin/farmers/{$this->profile->uuid}/reports/pdf")
        ->assertOk();
});

it('refuses a user with no farmer profile and no staff role, same as print', function () {
    $this->actingAs(User::factory()->create())
        ->get('/my-reports/pdf')
        ->assertForbidden();
});

it('refuses a guest', function () {
    $this->get('/my-reports/pdf')->assertRedirect('/login');
});

// --- CSV ---

it('downloads a csv with the right content type', function () {
    $response = $this->actingAs($this->farmerUser)->get('/my-reports/csv');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

it('the csv has a header row, the transaction, and opening/closing summary rows', function () {
    $response = $this->actingAs($this->farmerUser)->get('/my-reports/csv');

    $lines = explode("\n", trim($response->getContent()));

    expect($lines[0])->toContain('Date');
    expect($lines[0])->toContain('Reference');
    expect($lines[0])->toContain('Balance');

    expect(implode("\n", $lines))->toContain('Brought forward');
    expect(implode("\n", $lines))->toContain($this->record->reference);
    expect(implode("\n", $lines))->toContain('Totals');
});

it('the csv shows amounts in cedis, not pesewas', function () {
    $response = $this->actingAs($this->farmerUser)->get('/my-reports/csv');

    expect($response->getContent())->toContain('250.00');
    expect($response->getContent())->not->toContain('25000');
});

it('the csv respects the date range filter', function () {
    $response = $this->actingAs($this->farmerUser)->get(
        '/my-reports/csv?from=' . now()->addYear()->startOfYear()->toDateString()
            . '&to=' . now()->addYear()->endOfYear()->toDateString(),
    );

    expect($response->getContent())->not->toContain($this->record->reference);
});

it('an agent can download the csv for a farmer they hold', function () {
    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$this->profile->uuid}/reports/csv")
        ->assertOk();
});

it('an agent cannot download the csv for a farmer they do not hold', function () {
    $other = FarmerProfile::factory()->create();

    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$other->uuid}/reports/csv")
        ->assertNotFound();
});

it('an admin can download the csv for any farmer', function () {
    $this->actingAs($this->admin)
        ->get("/admin/farmers/{$this->profile->uuid}/reports/csv")
        ->assertOk();
});

it('refuses a csv download for a guest', function () {
    $this->get('/my-reports/csv')->assertRedirect('/login');
});
