<?php

use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerSubcategory;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $this->assetsCategory = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $drClass->id,
    ]);

    $this->incomeCategory = LedgerCategory::create([
        'name' => 'Income',
        'class_id' => $crClass->id,
    ]);
});

it('creates a subcategory belonging to a category', function () {
    $subcategory = LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    expect($subcategory->name)->toBe('Short Term Asset');
    expect($subcategory->category_id)->toBe($this->assetsCategory->id);
});

it('belongs to a category', function () {
    $subcategory = LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    expect($subcategory->category->name)->toBe('Assets');
});

it('enforces unique names within the same category', function () {
    LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    expect(fn() => LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]))->toThrow(QueryException::class);
});

it('allows the same name in a different category', function () {
    LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'General',
    ]);

    $subcategory = LedgerSubcategory::create([
        'category_id' => $this->incomeCategory->id,
        'name' => 'General',
    ]);

    expect($subcategory->name)->toBe('General');
});

it('soft deletes a subcategory', function () {
    $subcategory = LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $subcategory->delete();

    expect(LedgerSubcategory::find($subcategory->id))->toBeNull();
    expect(LedgerSubcategory::withTrashed()->find($subcategory->id))->not->toBeNull();
});
