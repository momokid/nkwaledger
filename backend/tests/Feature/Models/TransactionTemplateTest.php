<?php

use App\Models\FarmTypeCategory;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\TransactionTemplate;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assetsCategory = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $drClass->id,
    ]);

    $incomeCategory = LedgerCategory::create([
        'name' => 'Income',
        'class_id' => $crClass->id,
    ]);

    $assetSubcategory = LedgerSubcategory::create([
        'category_id' => $assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $incomeSubcategory = LedgerSubcategory::create([
        'category_id' => $incomeCategory->id,
        'name' => 'Farm Income',
    ]);

    $control = LedgerControl::create(['name' => 'Cash Ctrl']);
    $type = LedgerType::create(['name' => 'GL']);

    $this->cashAccount = LedgerAccount::create([
        'name' => 'Cash on Hand',
        'control_id' => $control->id,
        'subcategory_id' => $assetSubcategory->id,
        'type_id' => $type->id,
    ]);

    $this->salesAccount = LedgerAccount::create([
        'name' => 'Crop Sales',
        'control_id' => $control->id,
        'subcategory_id' => $incomeSubcategory->id,
        'type_id' => $type->id,
    ]);

    $this->validPayload = [
        'name' => 'I sold crops',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cashAccount->id,
        'credit_account_id' => $this->salesAccount->id,
        'settlement_side' => 'debit',
    ];
});

it('creates a transaction template with its required fields', function () {
    $template = TransactionTemplate::create($this->validPayload);

    expect($template->name)->toBe('I sold crops');
    expect($template->slug)->toBe('crop_sale');
    expect($template->transaction_type)->toBe('INCOME');
});

it('defaults to active, not a system row, and not needing a farm unit', function () {
    $template = TransactionTemplate::create($this->validPayload);

    expect($template->is_active)->toBeTrue();
    expect($template->is_system)->toBeFalse();
    expect($template->requires_farm_unit)->toBeFalse();
});

it('belongs to a debit account and a credit account', function () {
    $template = TransactionTemplate::create($this->validPayload);

    expect($template->debitAccount->name)->toBe('Cash on Hand');
    expect($template->creditAccount->name)->toBe('Crop Sales');
});

it('leaves the farm type category empty when none is given', function () {
    $template = TransactionTemplate::create($this->validPayload);

    expect($template->farm_type_category_id)->toBeNull();
    expect($template->farmTypeCategory)->toBeNull();
});

it('belongs to a farm type category when one is given', function () {
    $category = FarmTypeCategory::create(['name' => 'Livestock']);

    $template = TransactionTemplate::create([
        ...$this->validPayload,
        'farm_type_category_id' => $category->id,
    ]);

    expect($template->farmTypeCategory->name)->toBe('Livestock');
});

it('enforces unique slugs', function () {
    TransactionTemplate::create($this->validPayload);

    expect(fn() => TransactionTemplate::create([
        ...$this->validPayload,
        'name' => 'I sold maize',
    ]))->toThrow(QueryException::class);
});

it('rejects a template whose debit and credit accounts are the same', function () {
    expect(fn() => TransactionTemplate::create([
        ...$this->validPayload,
        'credit_account_id' => $this->cashAccount->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('soft deletes a transaction template', function () {
    $template = TransactionTemplate::create($this->validPayload);

    $template->delete();

    expect(TransactionTemplate::find($template->id))->toBeNull();
    expect(TransactionTemplate::withTrashed()->find($template->id))->not->toBeNull();
});
