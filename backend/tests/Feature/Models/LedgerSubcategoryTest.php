<?php

use App\Models\LedgerCategory;
use App\Models\LedgerFundamentalType;
use App\Models\LedgerSubcategory;
use Database\Seeders\LedgerFundamentalTypeSeeder;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->seed(LedgerFundamentalTypeSeeder::class);

    $asset = LedgerFundamentalType::where('name', 'Asset')->first();
    $this->assetsCategory = LedgerCategory::create([
        'fundamental_type_id' => $asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $income = LedgerFundamentalType::where('name', 'Income')->first();
    $this->incomeCategory = LedgerCategory::create([
        'fundamental_type_id' => $income->id,
        'name' => 'Income',
        'type' => 'Income',
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
