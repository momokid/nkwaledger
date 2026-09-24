<?php

use App\Models\KioskProductPriceHistory;

test('a price history row cannot be edited once written', function () {
    $row = KioskProductPriceHistory::factory()->create();

    expect(fn() => $row->update(['new_price' => 999]))->toThrow(RuntimeException::class);
});

test('a price history row cannot be deleted', function () {
    $row = KioskProductPriceHistory::factory()->create();

    expect(fn() => $row->delete())->toThrow(RuntimeException::class);
});
