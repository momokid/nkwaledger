<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\ReversalRequest;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

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

    TransactionTemplate::create([
        'name' => 'Correction of an earlier record',
        'slug' => 'correction',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'none',
    ]);

    $this->period = AccountingPeriod::create([
        'name' => 'This Period',
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->endOfMonth()->toDateString(),
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');

    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    $this->record = app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->saleTemplate->id,
        amount: '250',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->farmerUser->id,
    ));
});

// a failure nobody can see is a failure the user thinks did not happen
it('flashes a message when a cancellation cannot be agreed', function () {
    app(App\Services\Ledger\ReversalService::class)
        ->request($this->record, $this->admin, 'My own mistake.');

    $this->actingAs($this->admin)
        ->from('/admin/approvals')
        ->patch('/admin/reversals/' . ReversalRequest::first()->uuid . '/approve')
        ->assertSessionHas('error');
});

it('says why the cancellation was refused', function () {
    app(App\Services\Ledger\ReversalService::class)
        ->request($this->record, $this->admin, 'My own mistake.');

    $this->actingAs($this->admin)
        ->from('/admin/approvals')
        ->patch('/admin/reversals/' . ReversalRequest::first()->uuid . '/approve')
        ->assertSessionHas('error', 'Somebody else has to agree to this cancellation.');
});

it('flashes a message when a record cannot be saved', function () {
    $this->period->close($this->admin);

    $this->actingAs($this->farmerUser)
        ->from('/my-records/create')
        ->post('/my-records', [
            'transaction_template_id' => $this->saleTemplate->id,
            'amount' => '100',
            'settlement_account_id' => $this->cash->id,
            'transaction_date' => now()->toDateString(),
        ])
        ->assertSessionHas('error');
});

// a field problem still belongs under its field, not at the top of the page
it('keeps a field problem on its field', function () {
    $this->actingAs($this->farmerUser)
        ->post('/my-records', [
            'transaction_template_id' => $this->saleTemplate->id,
            'amount' => 'two fifty',
            'settlement_account_id' => $this->cash->id,
            'transaction_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('amount')
        ->assertSessionMissing('error');
});

it('flashes a message when a farm unit cannot be approved', function () {
    $unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->from('/admin/approvals')
        ->patch("/admin/farmers/{$this->profile->uuid}/units/{$unit->id}/approve")
        ->assertSessionHas('error');
});

it('flashes a message when a unit is already approved', function () {
    $unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'approved_at' => now(),
        'approved_by' => $this->farmerUser->id,
    ]);

    $this->actingAs($this->admin)
        ->from('/admin/approvals')
        ->patch("/admin/farmers/{$this->profile->uuid}/units/{$unit->id}/approve")
        ->assertSessionHas('error');
});
