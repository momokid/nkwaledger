<?php

use Illuminate\Support\Facades\Schema;

test('retired ledger type tables no longer exist', function () {
    expect(Schema::hasTable('ledger_account_types'))->toBeFalse();
    expect(Schema::hasTable('ledger_fundamental_types'))->toBeFalse();
});
