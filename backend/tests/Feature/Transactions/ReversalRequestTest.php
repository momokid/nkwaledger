<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\ReversalRequest;
use App\Models\Transaction;
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

    $category = FarmTypeCategory::create(['name' => 'Crop']);

    $farmType = FarmType::create([
        'name' => 'Maize',
        'category_id' => $category->id,
        'is_active' => true,
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

    AccountingPeriod::create([
        'name' => 'Test Period',
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

    $this->profile->farmTypes()->attach($farmType->id);

    $this->record = app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->saleTemplate->id,
        amount: '250',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->startOfMonth()->toDateString(),
        narration: 'Sold maize',
        recordedBy: $this->farmerUser->id,
    ));

    $this->reason = ['reason' => 'I typed the wrong amount.'];
});

// their record, their mistake, so they may ask
it('lets a farmer ask to cancel their own record', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason)
        ->assertRedirect();

    expect(ReversalRequest::where('transaction_id', $this->record->id)->exists())->toBeTrue();
});

it('records who asked and why', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    $request = ReversalRequest::first();

    expect($request->requested_by)->toBe($this->farmerUser->id);
    expect($request->reason)->toBe('I typed the wrong amount.');
    expect($request->status)->toBe(ReversalRequest::PENDING);
});

// asking is not the same as doing
it('changes nothing in the books when the ask is made', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    expect(Transaction::count())->toBe(1);
});

it('needs a reason', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", ['reason' => ''])
        ->assertSessionHasErrors('reason');
});

// one farmer never touches another farmer's books
it('refuses a record belonging to somebody else', function () {
    $stranger = FarmerProfile::factory()->create();

    $theirs = app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $stranger->id,
        transactionTemplateId: $this->saleTemplate->id,
        amount: '100',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->farmerUser->id,
    ));

    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$theirs->uuid}/cancel", $this->reason)
        ->assertNotFound();
});

it('refuses a second ask for the same record', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason)
        ->assertSessionHasErrors();

    expect(ReversalRequest::count())->toBe(1);
});

// the farmer should not have to ask twice to find out
it('marks the row as waiting on the list', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->where('statement.rows.0.cancel_state', 'waiting'));
});

it('marks a row that can still be cancelled', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->where('statement.rows.0.cancel_state', 'open'));
});

it('marks a row that has been cancelled', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    app(App\Services\Ledger\ReversalService::class)
        ->approve(ReversalRequest::first(), $this->admin);

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->where('statement.rows', function ($rows) {
            $original = collect($rows)->firstWhere('reference', $this->record->reference);

            return $original['cancel_state'] === 'cancelled';
        }));
});

// both stay on the page, neither is hidden
it('shows the correction as its own row', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    app(App\Services\Ledger\ReversalService::class)
        ->approve(ReversalRequest::first(), $this->admin);

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->has('statement.rows', 2));
});

it('leaves the farmer back where they started', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    app(App\Services\Ledger\ReversalService::class)
        ->approve(ReversalRequest::first(), $this->admin);

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->where('statement.closing_balance', 0));
});

// an agent asks on a farmer's behalf when they are asked to
it('lets an agent ask on a farmer they hold', function () {
    $this->actingAs($this->agent)
        ->post("/agent/farmers/{$this->profile->uuid}/records/{$this->record->uuid}/cancel", $this->reason)
        ->assertRedirect();

    expect(ReversalRequest::first()->requested_by)->toBe($this->agent->id);
});

it('hides a farmer the agent does not hold', function () {
    $other = FarmerProfile::factory()->create();

    $theirs = app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $other->id,
        transactionTemplateId: $this->saleTemplate->id,
        amount: '100',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(),
        recordedBy: $this->agent->id,
    ));

    $this->actingAs($this->agent)
        ->post("/agent/farmers/{$other->uuid}/records/{$theirs->uuid}/cancel", $this->reason)
        ->assertNotFound();
});

// a cancellation waiting on somebody belongs in the same list as everything else
it('puts the ask in the approvals queue', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    $this->actingAs($this->admin)
        ->get('/admin/approvals')
        ->assertInertia(fn($page) => $page->where('items.data', function ($items) {
            return collect($items)->pluck('kind')->contains('reversal');
        }));
});

it('shows the reason and the record in the queue', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    $this->actingAs($this->admin)
        ->get('/admin/approvals')
        ->assertInertia(fn($page) => $page->where('items.data', function ($items) {
            $row = collect($items)->firstWhere('kind', 'reversal');

            return $row['details']['reason'] === 'I typed the wrong amount.'
                && $row['details']['reference'] === $this->record->reference;
        }));
});

// whoever asks cannot be whoever agrees
it('does not offer the button to the person who asked', function () {
    $this->actingAs($this->agent)
        ->post("/agent/farmers/{$this->profile->uuid}/records/{$this->record->uuid}/cancel", $this->reason);

    $this->actingAs($this->agent)
        ->get('/agent/approvals')
        ->assertInertia(fn($page) => $page->where('items.data', function ($items) {
            $row = collect($items)->firstWhere('kind', 'reversal');

            return $row !== null && $row['can_approve'] === false;
        }));
});

it('lets somebody else agree from the queue', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    $request = ReversalRequest::first();

    $this->actingAs($this->admin)
        ->patch("/admin/reversals/{$request->uuid}/approve")
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ReversalRequest::APPROVED);
    expect(Transaction::count())->toBe(2);
});

// a refusal is part of the trail too
it('lets somebody else refuse from the queue', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    $request = ReversalRequest::first();

    $this->actingAs($this->admin)
        ->patch("/admin/reversals/{$request->uuid}/reject", ['reason' => 'The original was right.'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ReversalRequest::REJECTED);
    expect(Transaction::count())->toBe(1);
});

it('refuses approval by the person who asked', function () {
    $this->admin->givePermissionTo('transactions.reverse-request');

    $this->actingAs($this->admin)
        ->post("/admin/farmers/{$this->profile->uuid}/records/{$this->record->uuid}/cancel", $this->reason);

    $this->actingAs($this->admin)
        ->patch("/admin/reversals/" . ReversalRequest::first()->uuid . "/approve")
        ->assertSessionHasErrors();

    expect(Transaction::count())->toBe(1);
});

it('turns away somebody without the permission', function () {
    $this->actingAs($this->farmerUser)
        ->post("/my-records/{$this->record->uuid}/cancel", $this->reason);

    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)
        ->patch("/admin/reversals/" . ReversalRequest::first()->uuid . "/approve")
        ->assertForbidden();
});
