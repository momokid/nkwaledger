<?php

use App\Models\LedgerControl;

it('creates a ledger control with a name', function () {
    $control = LedgerControl::create(['name' => 'Cash Ctrl']);

    expect($control->name)->toBe('Cash Ctrl');
    expect($control->is_active)->toBeTrue();
});

it('enforces unique ledger control names', function () {
    LedgerControl::create(['name' => 'Cash Ctrl']);

    expect(fn() => LedgerControl::create(['name' => 'Cash Ctrl']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('soft deletes a ledger control', function () {
    $control = LedgerControl::create(['name' => 'Cash Ctrl']);

    $control->delete();

    expect(LedgerControl::find($control->id))->toBeNull();
    expect(LedgerControl::withTrashed()->find($control->id))->not->toBeNull();
});
