<?php

use App\Models\LedgerCategory;
use App\Models\LedgerFundamentalType;
use Database\Seeders\LedgerFundamentalTypeSeeder;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->seed(LedgerFundamentalTypeSeeder::class);
});

it('derives class from the debit normal balance of its fundamental type', function () {
    $asset = LedgerFundamentalType::where('name', 'Asset')->first();

    $category = LedgerCategory::create([
        'fundamental_type_id' => $asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    expect($category->class)->toBe('debit');
});

it('derives class from the credit normal balance of its fundamental type', function () {
    $income = LedgerFundamentalType::where('name', 'Income')->first();

    $category = LedgerCategory::create([
        'fundamental_type_id' => $income->id,
        'name' => 'Income',
        'type' => 'Income',
    ]);

    expect($category->class)->toBe('credit');
});

it('cannot have its class set manually and always derives it from the fundamental type', function () {
    $asset = LedgerFundamentalType::where('name', 'Asset')->first();

    $category = LedgerCategory::create([
        'fundamental_type_id' => $asset->id,
        'name' => 'Assets',
        'type' => 'GL',
        'class' => 'credit',
    ]);

    expect($category->class)->toBe('debit');
});

it('belongs to a fundamental type', function () {
    $liability = LedgerFundamentalType::where('name', 'Liability')->first();

    $category = LedgerCategory::create([
        'fundamental_type_id' => $liability->id,
        'name' => 'Liabilities',
        'type' => 'GL',
    ]);

    expect($category->fundamentalType->name)->toBe('Liability');
});

it('enforces unique category names', function () {
    $asset = LedgerFundamentalType::where('name', 'Asset')->first();

    LedgerCategory::create([
        'fundamental_type_id' => $asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    expect(fn() => LedgerCategory::create([
        'fundamental_type_id' => $asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]))->toThrow(QueryException::class);
});

it('soft deletes a ledger category', function () {
    $asset = LedgerFundamentalType::where('name', 'Asset')->first();

    $category = LedgerCategory::create([
        'fundamental_type_id' => $asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $category->delete();

    expect(LedgerCategory::find($category->id))->toBeNull();
    expect(LedgerCategory::withTrashed()->find($category->id))->not->toBeNull();
});
