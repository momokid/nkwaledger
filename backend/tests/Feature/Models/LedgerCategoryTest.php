<?php

use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->drClass = LedgerClass::create(['name' => 'Dr']);
    $this->crClass = LedgerClass::create(['name' => 'Cr']);
});

it('creates a ledger category with a name and class', function () {
    $category = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    expect($category->name)->toBe('Assets');
    expect($category->class_id)->toBe($this->drClass->id);
});

it('belongs to a class', function () {
    $category = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    expect($category->class->name)->toBe('Dr');
});

it('enforces unique category names', function () {
    LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    expect(fn() => LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->crClass->id,
    ]))->toThrow(QueryException::class);
});

it('soft deletes a ledger category', function () {
    $category = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $category->delete();

    expect(LedgerCategory::find($category->id))->toBeNull();
    expect(LedgerCategory::withTrashed()->find($category->id))->not->toBeNull();
});
