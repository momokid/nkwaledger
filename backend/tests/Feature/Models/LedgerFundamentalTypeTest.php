<?php

use App\Models\LedgerFundamentalType;
use Database\Seeders\LedgerFundamentalTypeSeeder;

it('seeds exactly five fundamental types', function () {
    $this->seed(LedgerFundamentalTypeSeeder::class);

    expect(LedgerFundamentalType::count())->toBe(5);
});

it('seeds the correct five fundamental type names', function () {
    $this->seed(LedgerFundamentalTypeSeeder::class);

    $names = LedgerFundamentalType::pluck('name')->all();

    expect($names)->toEqualCanonicalizing([
        'Asset',
        'Liability',
        'Equity',
        'Income',
        'Expense',
    ]);
});

it('assigns debit as normal balance for asset and expense', function () {
    $this->seed(LedgerFundamentalTypeSeeder::class);

    $asset = LedgerFundamentalType::where('name', 'Asset')->first();
    $expense = LedgerFundamentalType::where('name', 'Expense')->first();

    expect($asset->normal_balance)->toBe('debit');
    expect($expense->normal_balance)->toBe('debit');
});

it('assigns credit as normal balance for liability, equity, and income', function () {
    $this->seed(LedgerFundamentalTypeSeeder::class);

    $liability = LedgerFundamentalType::where('name', 'Liability')->first();
    $equity = LedgerFundamentalType::where('name', 'Equity')->first();
    $income = LedgerFundamentalType::where('name', 'Income')->first();

    expect($liability->normal_balance)->toBe('credit');
    expect($equity->normal_balance)->toBe('credit');
    expect($income->normal_balance)->toBe('credit');
});

it('enforces unique fundamental type names', function () {
    $this->seed(LedgerFundamentalTypeSeeder::class);

    expect(fn() => LedgerFundamentalType::create([
        'name' => 'Asset',
        'normal_balance' => 'debit',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
