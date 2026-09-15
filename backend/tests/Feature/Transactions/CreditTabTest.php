<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
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
    $this->receivable = $account('Accounts Receivable', $assetSub->id, true);
    $this->payable = $account('Accounts Payable', $assetSub->id, true);
    $this->sales = $account('Income on Sales', $incomeSub->id);
    $this->feed = $account('Expense on Feed', $expenseSub->id);

    $this->creditSaleTemplate = TransactionTemplate::create([
        'name' => 'I sold my produce',
        'slug' => 'produce_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->sales->id,
        'settlement_side' => 'debit',
        'allows_credit' => true,
    ]);

    $this->creditPurchaseTemplate = TransactionTemplate::create([
        'name' => 'I bought feed',
        'slug' => 'feed_purchase',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $this->feed->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
        'allows_credit' => true,
    ]);

    TransactionTemplate::create([
        'name' => 'Payment received',
        'slug' => 'payment_received',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->cash->id,
        'credit_account_id' => $this->receivable->id,
        'settlement_side' => 'debit',
    ]);

    TransactionTemplate::create([
        'name' => 'Payment made',
        'slug' => 'payment_made',
        'transaction_type' => 'ADJUSTMENT',
        'debit_account_id' => $this->payable->id,
        'credit_account_id' => $this->cash->id,
        'settlement_side' => 'credit',
    ]);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    // sold on credit
    $this->actingAs($this->farmerUser)->post('/my-records', [
        'transaction_template_id' => $this->creditSaleTemplate->id,
        'amount' => '500',
        'is_credit' => true,
        'transaction_date' => now()->toDateString(),
        'narration' => 'Sold maize on credit',
    ]);
    $this->creditSale = Transaction::where('transaction_template_id', $this->creditSaleTemplate->id)->first();

    // an ordinary cash sale, for contrast
    $this->actingAs($this->farmerUser)->post('/my-records', [
        'transaction_template_id' => $this->creditSaleTemplate->id,
        'amount' => '75',
        'settlement_account_id' => $this->cash->id,
        'transaction_date' => now()->toDateString(),
        'narration' => 'Sold maize for cash',
    ]);
});

it('shows only is_credit transactions on the credit tab, with the full amount outstanding', function () {
    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page
            ->has('creditRows', 1)
            ->where('creditRows.0.uuid', $this->creditSale->uuid)
            ->where('creditRows.0.outstanding', 50000));
});

it('reduces the outstanding amount after a partial payment', function () {
    $this->actingAs($this->farmerUser)->post("/my-records/{$this->creditSale->uuid}/settle", [
        'amount' => '200',
        'settlement_account_id' => $this->cash->id,
    ]);

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->where('creditRows.0.outstanding', 30000));
});

it('posts a real settlement transaction and records the credit_settlements row', function () {
    $before = Transaction::count();

    $this->actingAs($this->farmerUser)->post("/my-records/{$this->creditSale->uuid}/settle", [
        'amount' => '200',
        'settlement_account_id' => $this->cash->id,
    ]);

    expect(Transaction::count())->toBe($before + 1);
    $this->assertDatabaseHas('credit_settlements', [
        'transaction_id' => $this->creditSale->id,
        'amount_minor' => 20000,
    ]);
});

it('allows settling the full remaining amount, marking it paid', function () {
    $this->actingAs($this->farmerUser)->post("/my-records/{$this->creditSale->uuid}/settle", [
        'amount' => '500',
        'settlement_account_id' => $this->cash->id,
    ]);

    $this->actingAs($this->farmerUser)
        ->get('/my-records')
        ->assertInertia(fn($page) => $page->where('creditRows.0.outstanding', 0));
});

it('rejects a payment larger than what is still outstanding, with a clear error', function () {
    $response = $this->actingAs($this->farmerUser)->post("/my-records/{$this->creditSale->uuid}/settle", [
        'amount' => '600',
        'settlement_account_id' => $this->cash->id,
    ]);

    $response->assertSessionHasErrors('amount');
    $this->assertDatabaseMissing('credit_settlements', ['transaction_id' => $this->creditSale->id]);
});

it('rejects a second payment that would push the total above the original amount', function () {
    $this->actingAs($this->farmerUser)->post("/my-records/{$this->creditSale->uuid}/settle", [
        'amount' => '400',
        'settlement_account_id' => $this->cash->id,
    ]);

    $response = $this->actingAs($this->farmerUser)->post("/my-records/{$this->creditSale->uuid}/settle", [
        'amount' => '200',
        'settlement_account_id' => $this->cash->id,
    ]);

    $response->assertSessionHasErrors('amount');
});

it('refuses to settle a transaction that was never on credit', function () {
    $cashSale = Transaction::where('amount_minor', 7500)->first();

    $response = $this->actingAs($this->farmerUser)->post("/my-records/{$cashSale->uuid}/settle", [
        'amount' => '75',
        'settlement_account_id' => $this->cash->id,
    ]);

    $response->assertSessionHasErrors('amount');
});

it('refuses to settle into Accounts Receivable or Accounts Payable directly', function () {
    $response = $this->actingAs($this->farmerUser)->post("/my-records/{$this->creditSale->uuid}/settle", [
        'amount' => '200',
        'settlement_account_id' => $this->payable->id,
    ]);

    $response->assertSessionHasErrors('settlement_account_id');
});

it('refuses to settle a transaction belonging to another farmer', function () {
    $stranger = User::factory()->create();
    $stranger->assignRole('farmer');
    FarmerProfile::factory()->create(['user_id' => $stranger->id]);

    $this->actingAs($stranger)
        ->post("/my-records/{$this->creditSale->uuid}/settle", [
            'amount' => '200',
            'settlement_account_id' => $this->cash->id,
        ])
        ->assertNotFound();
});
