<?php

use App\Models\LedgerType;

it('creates a ledger type with a name', function () {
    $type = LedgerType::create(['name' => 'GL']);

    expect($type->name)->toBe('GL');
    expect($type->is_active)->toBeTrue();
});

it('enforces unique ledger type names', function () {
    LedgerType::create(['name' => 'GL']);

    expect(fn() => LedgerType::create(['name' => 'GL']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('soft deletes a ledger type', function () {
    $type = LedgerType::create(['name' => 'GL']);

    $type->delete();

    expect(LedgerType::find($type->id))->toBeNull();
    expect(LedgerType::withTrashed()->find($type->id))->not->toBeNull();
});
