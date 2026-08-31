<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

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

    $account = function (string $name, int $subcategoryId, bool $isSettlement = false) use ($control, $type) {
        return LedgerAccount::create([
            'name' => $name,
            'control_id' => $control->id,
            'subcategory_id' => $subcategoryId,
            'type_id' => $type->id,
            'is_settlement' => $isSettlement,
        ]);
    };

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $this->momo = $account('Momo A/C', $assetSub->id, true);
    $this->sales = $account('Income on Sales', $incomeSub->id);
    $this->feed = $account('Expense on Feed', $expenseSub->id);

    $this->cropCategory = FarmTypeCategory::create(['name' => 'Crop']);
    $this->livestockCategory = FarmTypeCategory::create(['name' => 'Livestock']);

    $this->cropType = FarmType::create([
        'name' => 'Maize',
        'category_id' => $this->cropCategory->id,
        'is_active' => true,
    ]);

    $this->cropTemplate = TransactionTemplate::create([
        'name' => 'I sold my produce',
        'slug' => 'produce_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
        'farm_type_category_id' => $this->cropCategory->id,
    ]);

    $this->livestockTemplate = TransactionTemplate::create([
        'name' => 'I bought feed',
        'slug' => 'feed_purchase',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $this->feed->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
        'requires_farm_unit' => true,
        'farm_type_category_id' => $this->livestockCategory->id,
    ]);

    // never shown in the picker, since a farmer does not cancel their own records
    $this->correctionTemplate = TransactionTemplate::create([
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

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');

    $this->profile = FarmerProfile::factory()->create([
        'user_id' => $this->farmerUser->id,
        'assigned_agent_id' => $this->agent->id,
    ]);

    $this->profile->farmTypes()->attach($this->cropType->id);

    $this->unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->profile->id,
        'approved_at' => now()->subMonth(),
    ]);

    $this->payload = [
        'transaction_template_id' => $this->cropTemplate->id,
        'amount' => '250.75',
        'settlement_account_id' => $this->cash->id,
        'transaction_date' => now()->toDateString(),
        'narration' => 'Sold maize at Kejetia',
    ];
});

// the farmer's own page, with no farmer in the address
it('shows the recording page to a farmer', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-records/create')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Transactions/Create'));
});

it('turns a stranger away from the recording page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/my-records/create')
        ->assertForbidden();
});

it('sends the farmer their own templates', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-records/create')
        ->assertInertia(fn($page) => $page
            ->has('templates', 1)
            ->where('templates.0.id', $this->cropTemplate->id));
});

// a crop farmer has no animals to feed
it('leaves out templates for a kind of farming they do not do', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-records/create')
        ->assertInertia(fn($page) => $page
            ->where('templates.0.name', 'I sold my produce'));
});

// a farmer never cancels their own record
it('never offers a correction in the picker', function () {
    $this->profile->farmTypes()->detach();

    $this->actingAs($this->farmerUser)
        ->get('/my-records/create')
        ->assertInertia(fn($page) => $page->has('templates', 0));
});

it('sends only the accounts money can sit in', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-records/create')
        ->assertInertia(fn($page) => $page->has('settlementAccounts', 2));
});

it('sends the farm units the farmer can pick from', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-records/create')
        ->assertInertia(fn($page) => $page->has('farmUnits', 1));
});

it('records what the farmer typed', function () {
    $this->actingAs($this->farmerUser)
        ->post('/my-records', $this->payload)
        ->assertRedirect();

    $transaction = Transaction::first();

    expect($transaction->amount_minor)->toBe(25075);
    expect($transaction->farmer_profile_id)->toBe($this->profile->id);
    expect($transaction->channel)->toBe('web');
});

it('says which record was saved', function () {
    $response = $this->actingAs($this->farmerUser)->post('/my-records', $this->payload);

    $response->assertSessionHas('success');
    $response->assertSessionHas('reference', Transaction::first()->reference);
});

it('marks the farmer as the one who recorded it', function () {
    $this->actingAs($this->farmerUser)->post('/my-records', $this->payload);

    expect(Transaction::first()->recorded_by)->toBe($this->farmerUser->id);
});

// the money is only ever written as a whole number of pesewas
it('refuses an amount with too many decimal places', function () {
    $this->actingAs($this->farmerUser)
        ->post('/my-records', ['amount' => '250.755'] + $this->payload)
        ->assertSessionHasErrors('amount');
});

it('refuses an amount that is not a number', function () {
    $this->actingAs($this->farmerUser)
        ->post('/my-records', ['amount' => 'two fifty'] + $this->payload)
        ->assertSessionHasErrors('amount');
});

it('refuses a date in the future', function () {
    $this->actingAs($this->farmerUser)
        ->post('/my-records', ['transaction_date' => now()->addDay()->toDateString()] + $this->payload)
        ->assertSessionHasErrors('transaction_date');
});

it('refuses a template the farmer cannot use', function () {
    $this->actingAs($this->farmerUser)
        ->post('/my-records', ['transaction_template_id' => $this->livestockTemplate->id] + $this->payload)
        ->assertSessionHasErrors('transaction_template_id');
});

// nobody records against another farmer's pen
it('refuses a farm unit belonging to somebody else', function () {
    $stranger = FarmUnit::factory()->create(['approved_at' => now()]);

    $this->profile->farmTypes()->attach(FarmType::create([
        'name' => 'Goats',
        'category_id' => $this->livestockCategory->id,
        'is_active' => true,
    ])->id);

    $this->actingAs($this->farmerUser)
        ->post('/my-records', [
            'transaction_template_id' => $this->livestockTemplate->id,
            'farm_unit_id' => $stranger->id,
        ] + $this->payload)
        ->assertSessionHasErrors('farm_unit_id');
});

it('refuses an account money cannot sit in', function () {
    $this->actingAs($this->farmerUser)
        ->post('/my-records', ['settlement_account_id' => $this->sales->id] + $this->payload)
        ->assertSessionHasErrors('settlement_account_id');
});

it('writes nothing when the form is wrong', function () {
    $this->actingAs($this->farmerUser)
        ->post('/my-records', ['amount' => '0'] + $this->payload);

    expect(Transaction::count())->toBe(0);
});

// the agent's version, with the farmer named in the address
it('shows the recording page to an agent', function () {
    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$this->profile->uuid}/records/create")
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Transactions/Create'));
});

it('records what an agent typed for a farmer', function () {
    $this->actingAs($this->agent)
        ->post("/agent/farmers/{$this->profile->uuid}/records", $this->payload)
        ->assertRedirect();

    expect(Transaction::first()->farmer_profile_id)->toBe($this->profile->id);
    expect(Transaction::first()->recorded_by)->toBe($this->agent->id);
});

// an agent works their own book and nobody else's
it('hides a farmer the agent does not hold', function () {
    $other = FarmerProfile::factory()->create();

    $this->actingAs($this->agent)
        ->get("/agent/farmers/{$other->uuid}/records/create")
        ->assertNotFound();
});

// a farmer learns nothing from the agent page, not even that it is there
it('turns a farmer away from the agent page', function () {
    $this->actingAs($this->farmerUser)
        ->get("/agent/farmers/{$this->profile->uuid}/records/create")
        ->assertNotFound();
});
