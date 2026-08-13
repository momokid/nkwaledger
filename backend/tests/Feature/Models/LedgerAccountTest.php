<?php

use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
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

    $this->assetSubcategory = LedgerSubcategory::create([
        'category_id' => $assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $this->incomeSubcategory = LedgerSubcategory::create([
        'category_id' => $incomeCategory->id,
        'name' => 'Program Revenues',
    ]);

    $this->control = LedgerControl::create(['name' => 'Cash Ctrl']);
    $this->glType = LedgerType::create(['name' => 'GL']);
});

it('creates a ledger account with its required relationships', function () {
    $account = LedgerAccount::create([
        'name' => 'Cash & MoMo',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->assetSubcategory->id,
        'type_id' => $this->glType->id,
    ]);

    expect($account->name)->toBe('Cash & MoMo');
    expect($account->is_active)->toBeTrue();
    expect($account->is_system)->toBeFalse();
    expect($account->account_code)->toBeNull();
});

it('stores an optional account code', function () {
    $account = LedgerAccount::create([
        'name' => 'Cash & MoMo',
        'account_code' => '1001',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->assetSubcategory->id,
        'type_id' => $this->glType->id,
    ]);

    expect($account->account_code)->toBe('1001');
});

it('belongs to a control, subcategory, and type', function () {
    $account = LedgerAccount::create([
        'name' => 'Cash & MoMo',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->assetSubcategory->id,
        'type_id' => $this->glType->id,
    ]);

    expect($account->control->name)->toBe('Cash Ctrl');
    expect($account->subcategory->name)->toBe('Short Term Asset');
    expect($account->type->name)->toBe('GL');
});

it('derives a debit class from its subcategory category', function () {
    $account = LedgerAccount::create([
        'name' => 'Cash & MoMo',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->assetSubcategory->id,
        'type_id' => $this->glType->id,
    ]);

    expect($account->class)->toBe('Dr');
});

it('derives a credit class from its subcategory category', function () {
    $account = LedgerAccount::create([
        'name' => 'Crop Sales',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->incomeSubcategory->id,
        'type_id' => $this->glType->id,
    ]);

    expect($account->class)->toBe('Cr');
});

it('enforces unique account names', function () {
    LedgerAccount::create([
        'name' => 'Cash & MoMo',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->assetSubcategory->id,
        'type_id' => $this->glType->id,
    ]);

    expect(fn() => LedgerAccount::create([
        'name' => 'Cash & MoMo',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->assetSubcategory->id,
        'type_id' => $this->glType->id,
    ]))->toThrow(QueryException::class);
});

it('blocks deletion of a system account', function () {
    $account = LedgerAccount::create([
        'name' => 'Cash & MoMo',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->assetSubcategory->id,
        'type_id' => $this->glType->id,
        'is_system' => true,
    ]);

    expect(fn() => $account->delete())->toThrow(RuntimeException::class);
    expect(LedgerAccount::find($account->id))->not->toBeNull();
});

it('soft deletes a non-system account', function () {
    $account = LedgerAccount::create([
        'name' => 'Cash & MoMo',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->assetSubcategory->id,
        'type_id' => $this->glType->id,
    ]);

    $account->delete();

    expect(LedgerAccount::find($account->id))->toBeNull();
    expect(LedgerAccount::withTrashed()->find($account->id))->not->toBeNull();
});
