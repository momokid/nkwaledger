<?php

use App\Models\LedgerAccount;
use App\Models\LedgerAccountType;
use Illuminate\Database\QueryException;
use RuntimeException;

test('normal_balance is read live from the account type', function () {
    $type = LedgerAccountType::create(['name' => 'Asset', 'normal_balance' => 'debit']);
    $account = LedgerAccount::create(['name' => 'Cash/MoMo', 'type_id' => $type->id]);

    expect($account->normal_balance)->toBe('debit');
});

test('a credit-normal type is reflected on its accounts', function () {
    $type = LedgerAccountType::create(['name' => 'Liability', 'normal_balance' => 'credit']);
    $account = LedgerAccount::create(['name' => 'Loan Payable', 'type_id' => $type->id]);

    expect($account->normal_balance)->toBe('credit');
});

test('normal_balance updates automatically if the type\'s normal_balance changes', function () {
    $type = LedgerAccountType::create(['name' => 'Custom', 'normal_balance' => 'debit']);
    $account = LedgerAccount::create(['name' => 'Custom Account', 'type_id' => $type->id]);

    expect($account->fresh()->normal_balance)->toBe('debit');

    $type->update(['normal_balance' => 'credit']);

    expect($account->fresh()->normal_balance)->toBe('credit');
});

test('a ledger account can be created without a type', function () {
    $account = LedgerAccount::create(['name' => 'Uncategorized']);

    expect($account->type_id)->toBeNull();
    expect($account->normal_balance)->toBeNull();
});

test('ledger account name must be unique', function () {
    LedgerAccount::create(['name' => 'Cash/MoMo']);

    expect(fn() => LedgerAccount::create(['name' => 'Cash/MoMo']))
        ->toThrow(QueryException::class);
});

test('a new ledger account defaults to not system and active', function () {
    $account = LedgerAccount::create(['name' => 'Cash/MoMo']);

    expect($account->is_system)->toBeFalse();
    expect($account->is_active)->toBeTrue();
});

test('a ledger account can be marked as a system account', function () {
    $account = LedgerAccount::create(['name' => 'Cash/MoMo', 'is_system' => true]);

    expect($account->is_system)->toBeTrue();
});

test('a ledger account can be deactivated without being deleted', function () {
    $account = LedgerAccount::create(['name' => 'Cash/MoMo']);

    $account->update(['is_active' => false]);

    expect($account->fresh()->is_active)->toBeFalse();
    expect(LedgerAccount::find($account->id))->not->toBeNull();
});

test('a non-system ledger account can be soft deleted', function () {
    $account = LedgerAccount::create(['name' => 'Cash/MoMo', 'is_system' => false]);

    $account->delete();

    expect(LedgerAccount::find($account->id))->toBeNull();
    expect(LedgerAccount::withTrashed()->find($account->id))->not->toBeNull();
});

test('a system ledger account cannot be deleted', function () {
    $account = LedgerAccount::create(['name' => 'Cash/MoMo', 'is_system' => true]);

    expect(fn() => $account->delete())->toThrow(RuntimeException::class);
    expect(LedgerAccount::find($account->id))->not->toBeNull();
});

test('a ledger account resolves its type relationship', function () {
    $type = LedgerAccountType::create(['name' => 'Income', 'normal_balance' => 'credit']);
    $account = LedgerAccount::create(['name' => 'Crop Sales', 'type_id' => $type->id]);

    expect($account->type->name)->toBe('Income');
});
