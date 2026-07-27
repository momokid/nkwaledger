<?php

use App\Models\LedgerAccountType;
use Illuminate\Database\QueryException;

test('a ledger account type can be created', function () {
    $type = LedgerAccountType::create(['name' => 'Asset', 'normal_balance' => 'debit']);

    expect($type->name)->toBe('Asset');
    expect($type->normal_balance)->toBe('debit');
});

test('a ledger account type can be credit normal', function () {
    $type = LedgerAccountType::create(['name' => 'Liability', 'normal_balance' => 'credit']);

    expect($type->normal_balance)->toBe('credit');
});

test('normal_balance must be debit or credit', function () {
    expect(fn() => LedgerAccountType::create(['name' => 'Mystery Type', 'normal_balance' => 'sideways']))
        ->toThrow(InvalidArgumentException::class);
});

test('ledger account type name must be unique', function () {
    LedgerAccountType::create(['name' => 'Asset', 'normal_balance' => 'debit']);

    expect(fn() => LedgerAccountType::create(['name' => 'Asset', 'normal_balance' => 'credit']))
        ->toThrow(QueryException::class);
});

test('a ledger account type can be soft deleted', function () {
    $type = LedgerAccountType::create(['name' => 'Equity', 'normal_balance' => 'credit']);

    $type->delete();

    expect(LedgerAccountType::find($type->id))->toBeNull();
    expect(LedgerAccountType::withTrashed()->find($type->id))->not->toBeNull();
});
