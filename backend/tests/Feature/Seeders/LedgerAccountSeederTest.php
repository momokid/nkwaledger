<?php

use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use Database\Seeders\LedgerAccountSeeder;

it('seeds the two classes', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerClass::count())->toBe(2);
    $this->assertDatabaseHas('ledger_classes', ['name' => 'Dr']);
    $this->assertDatabaseHas('ledger_classes', ['name' => 'Cr']);
});

it('seeds every account', function () {
    $this->seed(LedgerAccountSeeder::class);

    foreach (['Cash A/C', 'Momo A/C', 'Bank A/C', 'Livestock A/C', 'Fish Stock A/C', 'Stated Capital'] as $name) {
        expect(LedgerAccount::where('name', $name)->exists())->toBeTrue();
    }
});

it('seeds the money accounts', function () {
    $this->seed(LedgerAccountSeeder::class);

    foreach (['Cash A/C', 'Momo A/C', 'Bank A/C'] as $name) {
        $this->assertDatabaseHas('ledger_accounts', ['name' => $name]);
    }
});

it('seeds the accounts for things the farmer owns', function () {
    $this->seed(LedgerAccountSeeder::class);

    foreach (['Livestock A/C', 'Crops in Field A/C', 'Harvested Produce A/C'] as $name) {
        $this->assertDatabaseHas('ledger_accounts', ['name' => $name]);
    }
});

it('seeds the income accounts', function () {
    $this->seed(LedgerAccountSeeder::class);

    foreach (['Income on Sales', 'Other Income'] as $name) {
        $this->assertDatabaseHas('ledger_accounts', ['name' => $name]);
    }
});

it('seeds the expense accounts', function () {
    $this->seed(LedgerAccountSeeder::class);

    foreach (
        [
            'Expense on Farm Input',
            'Expense on Feed',
            'Expense on Medicine',
            'Expense on Labour',
            'Expense on Transport',
            'Loss on Farm Assets',
        ] as $name
    ) {
        $this->assertDatabaseHas('ledger_accounts', ['name' => $name]);
    }
});

it('seeds what is owed and what is owned', function () {
    $this->seed(LedgerAccountSeeder::class);

    $this->assertDatabaseHas('ledger_accounts', ['name' => 'Loan Payable']);
    $this->assertDatabaseHas('ledger_accounts', ['name' => 'Stated Capital']);
});

// money and things owned sit on the debit side
it('puts assets on the debit side', function () {
    $this->seed(LedgerAccountSeeder::class);

    foreach (['Cash A/C', 'Livestock A/C', 'Harvested Produce A/C'] as $name) {
        expect(LedgerAccount::where('name', $name)->first()->class)->toBe('Dr');
    }
});

it('puts income on the credit side', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::where('name', 'Income on Sales')->first()->class)->toBe('Cr');
});

it('puts expenses on the debit side', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::where('name', 'Expense on Feed')->first()->class)->toBe('Dr');
});

// a loan is money owed, so it grows on the credit side
it('puts a loan on the credit side', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::where('name', 'Loan Payable')->first()->class)->toBe('Cr');
});

it('puts stated capital on the credit side', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::where('name', 'Stated Capital')->first()->class)->toBe('Cr');
});

// these are the platform's own accounts, not something an admin should delete
it('marks every account as a system row', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::where('is_system', false)->count())->toBe(0);
});

it('makes every account active', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::where('is_active', false)->count())->toBe(0);
});

it('gives every account a code', function () {
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::whereNull('account_code')->count())->toBe(0);
});

it('can run twice without duplicating rows', function () {
    $this->seed(LedgerAccountSeeder::class);
    $before = LedgerAccount::count();

    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::count())->toBe($before)
        ->and(LedgerClass::count())->toBe(2)
        ->and(LedgerCategory::count())->toBe(5);
});

// the templates look accounts up by name, so both seeders have to work together
// the seeder skips a template whose accounts are missing, so none should be skipped here
it('leaves the transaction templates able to find their accounts', function () {
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(Database\Seeders\TransactionTemplateSeeder::class);

    foreach (['produce_sale', 'input_purchase', 'correction'] as $slug) {
        $this->assertDatabaseHas('transaction_templates', ['slug' => $slug]);
    }
});
