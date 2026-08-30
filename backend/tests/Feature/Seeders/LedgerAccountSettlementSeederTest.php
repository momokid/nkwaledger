<?php

use App\Models\LedgerAccount;
use Database\Seeders\LedgerAccountSeeder;

// the only three places a farmer's money can sit
it('ticks the three money accounts', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::settlement()->pluck('name')->sort()->values()->all())
        ->toBe(['Bank A/C', 'Cash A/C', 'Momo A/C']);
});

it('leaves every other account unticked', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::where('is_settlement', false)->count())->toBe(13);
});

// running the seeder twice must not change anything
it('keeps the ticks when it runs again', function () {
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::settlement()->count())->toBe(3);
});
