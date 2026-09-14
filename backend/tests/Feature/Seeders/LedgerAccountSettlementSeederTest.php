<?php

use App\Models\LedgerAccount;
use Database\Seeders\LedgerAccountSeeder;

// Cash/MoMo/Bank are money the farmer actually holds; Receivable/Payable are ticked
// too, since settling a sale or purchase against one of these is what "on credit" means
it('ticks the money accounts and the receivable/payable accounts', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::settlement()->pluck('name')->sort()->values()->all())
        ->toBe(['Accounts Payable', 'Accounts Receivable', 'Bank A/C', 'Cash A/C', 'Momo A/C']);
});

it('leaves every other account unticked', function () {
    $this->seed(LedgerAccountSeeder::class);

    foreach (['Livestock A/C', 'Fish Stock A/C', 'Income on Sales', 'Loan Payable'] as $name) {
        expect(LedgerAccount::where('name', $name)->first()->is_settlement)->toBeFalse();
    }
});

// running the seeder twice must not change anything
it('keeps the ticks when it runs again', function () {
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::settlement()->count())->toBe(5);
});
