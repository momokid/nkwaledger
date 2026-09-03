<?php

use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\TransactionTemplate;
use Database\Seeders\TransactionTemplateSeeder;

beforeEach(function () {
    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assets = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $income = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);
    $expenses = LedgerCategory::create(['name' => 'Expenses', 'class_id' => $drClass->id]);

    $assetSub = LedgerSubcategory::create(['category_id' => $assets->id, 'name' => 'Short Term Asset']);
    $incomeSub = LedgerSubcategory::create(['category_id' => $income->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => $expenses->id, 'name' => 'Farm Expense']);

    $control = LedgerControl::create(['name' => 'Cash Ctrl']);
    $type = LedgerType::create(['name' => 'GL']);

    foreach (
        [
            'Cash A/C' => $assetSub->id,
            'Momo A/C' => $assetSub->id,
            'Income on Sales' => $incomeSub->id,
            'Expense on Farm Input' => $expenseSub->id,
        ] as $name => $subcategoryId
    ) {
        LedgerAccount::create([
            'name' => $name,
            'control_id' => $control->id,
            'subcategory_id' => $subcategoryId,
            'type_id' => $type->id,
        ]);
    }
});

// names the templates it needs, so adding more never breaks this
it('seeds the default transaction templates', function () {
    $this->seed(TransactionTemplateSeeder::class);

    $this->assertDatabaseHas('transaction_templates', ['slug' => 'produce_sale']);
    $this->assertDatabaseHas('transaction_templates', ['slug' => 'input_purchase']);
});

it('marks every seeded template as a system row', function () {
    $this->seed(TransactionTemplateSeeder::class);

    expect(TransactionTemplate::where('is_system', false)->count())->toBe(0);
});

it('points each template at the right accounts', function () {
    $this->seed(TransactionTemplateSeeder::class);

    $sale = TransactionTemplate::where('slug', 'produce_sale')->first();

    expect($sale->debitAccount->name)->toBe('Cash A/C');
    expect($sale->creditAccount->name)->toBe('Income on Sales');
    expect($sale->settlement_side)->toBe('debit');
});

it('replaces the credit leg on a purchase', function () {
    $this->seed(TransactionTemplateSeeder::class);

    $purchase = TransactionTemplate::where('slug', 'input_purchase')->first();

    expect($purchase->debitAccount->name)->toBe('Expense on Farm Input');
    expect($purchase->creditAccount->name)->toBe('Cash A/C');
    expect($purchase->settlement_side)->toBe('credit');
});

it('can run twice without duplicating rows', function () {
    $this->seed(TransactionTemplateSeeder::class);
    $this->seed(TransactionTemplateSeeder::class);

    $duplicated = TransactionTemplate::query()
        ->selectRaw('slug, COUNT(*) as total')
        ->groupBy('slug')
        ->having('total', '>', 1)
        ->count();

    expect($duplicated)->toBe(0);
});

it('skips a template whose accounts are missing', function () {
    LedgerAccount::where('name', 'Income on Sales')->forceDelete();

    $this->seed(TransactionTemplateSeeder::class);

    expect(TransactionTemplate::count())->toBe(1);
    $this->assertDatabaseMissing('transaction_templates', ['slug' => 'produce_sale']);
});

// a reversal has nothing to hang off without this one
it('seeds a template for corrections', function () {
    $this->seed(TransactionTemplateSeeder::class);

    $this->assertDatabaseHas('transaction_templates', [
        'slug' => 'correction',
        'transaction_type' => 'ADJUSTMENT',
    ]);
});

it('needs no farm unit for a correction', function () {
    $this->seed(TransactionTemplateSeeder::class);

    expect(TransactionTemplate::where('slug', 'correction')->first()->requires_farm_unit)
        ->toBeFalse();
});
