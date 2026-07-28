<?php

use App\Models\LedgerClass;

it('creates a ledger class with a name', function () {
    $class = LedgerClass::create(['name' => 'Dr']);

    expect($class->name)->toBe('Dr');
    expect($class->is_active)->toBeTrue();
});

it('enforces unique ledger class names', function () {
    LedgerClass::create(['name' => 'Dr']);

    expect(fn () => LedgerClass::create(['name' => 'Dr']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('soft deletes a ledger class', function () {
    $class = LedgerClass::create(['name' => 'Dr']);

    $class->delete();

    expect(LedgerClass::find($class->id))->toBeNull();
    expect(LedgerClass::withTrashed()->find($class->id))->not->toBeNull();
});