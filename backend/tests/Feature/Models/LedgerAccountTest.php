<?php

use App\Models\LedgerAccount;
use Illuminate\Database\QueryException;

test('asset accounts are debit normal', function () {
    $account = LedgerAccount::create(['name' => 'Cash/MoMo', 'type' => 'asset']);

    expect($account->normal_balance)->toBe('debit');
});

test('expense accounts are debit normal', function () {
    $account = LedgerAccount::create(['name' => 'Fertilizer Expense', 'type' => 'expense']);

    expect($account->normal_balance)->toBe('debit');
});

test('liability accounts are credit normal', function () {
    $account = LedgerAccount::create(['name' => 'Loan Payable', 'type' => 'liability']);

    expect($account->normal_balance)->toBe('credit');
});

test('equity accounts are credit normal', function () {
    $account = LedgerAccount::create(['name' => 'Owner Equity', 'type' => 'equity']);

    expect($account->normal_balance)->toBe('credit');
});

test('income accounts are credit normal', function () {
    $account = LedgerAccount::create(['name' => 'Crop Income', 'type' => 'income']);

    expect($account->normal_balance)->toBe('credit');
});

test('normal_balance cannot be set manually and is always derived from type', function () {
    $account = LedgerAccount::create([
        'name' => 'Crop Income',
        'type' => 'income',
        'normal_balance' => 'debit',
    ]);

    expect($account->normal_balance)->toBe('credit');
});

test('type must be a recognized value', function () {
    expect(fn () => LedgerAccount::create(['name' => 'Mystery Account', 'type' => 'contra']))
        ->toThrow(InvalidArgumentException::class);
});

test('ledger account name must be unique', function () {
    LedgerAccount::create(['name' => 'Cash/MoMo', 'type' => 'asset']);

    expect(fn () => LedgerAccount::create(['name' => 'Cash/MoMo', 'type' => 'asset']))
        ->toThrow(QueryException::class);
});

test('a new ledger account defaults to not system and active', function () {
    $account = LedgerAccount::create(['name' => 'Cash/MoMo', 'type' => 'asset']);

    expect($account->is_system)->toBeFalse();
    expect($account->is_active)->toBeTrue();
});

test('a ledger account can be marked as a system account', function () {
    $account = LedgerAccount::create([
        'name' => 'Cash/MoMo',
        'type' => 'asset',
        'is_system' => true,
    ]);

    expect($account->is_system)->toBeTrue();
});

test('a ledger account can be deactivated without being deleted', function () {
    $account = LedgerAccount::create(['name' => 'Cash/MoMo', 'type' => 'asset']);

    $account->update(['is_active' => false]);

    expect($account->fresh()->is_active)->toBeFalse();
    expect(LedgerAccount::find($account->id))->not->toBeNull();
});

test('a ledger account can be soft deleted', function () {
    $account = LedgerAccount::create(['name' => 'Cash/MoMo', 'type' => 'asset']);

    $account->delete();

    expect(LedgerAccount::find($account->id))->toBeNull();
    expect(LedgerAccount::withTrashed()->find($account->id))->not->toBeNull();
});