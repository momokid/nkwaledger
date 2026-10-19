<?php

// The six everyday money-in and money-out records that need no farm unit and no quantity, so a phone can log them.

use App\Models\FarmTypeCategory;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\TransactionTemplateSeeder;

beforeEach(function () {
    foreach (['Aquatic', 'Crop', 'Livestock'] as $name) {
        FarmTypeCategory::firstOrCreate(['name' => $name]);
    }

    $this->seed(LedgerAccountSeeder::class);
    $this->seed(TransactionTemplateSeeder::class);
});

dataset('everyday', [
    'grant' => ['grant_income', 'I received a grant or support', 'INCOME', 'Cash A/C', 'Grants and Support'],
    'hire' => ['hire_income', 'I hired out my land or equipment', 'INCOME', 'Cash A/C', 'Hire Income'],
    'byproduct' => ['byproduct_income', 'I sold manure or by-products', 'INCOME', 'Cash A/C', 'By-product Income'],
    'rent' => ['rent_cost', 'I paid rent for land', 'EXPENSE', 'Expense on Rent', 'Cash A/C'],
    'utilities' => ['utilities_cost', 'I paid for water or electricity', 'EXPENSE', 'Expense on Utilities', 'Cash A/C'],
    'repairs' => ['repairs_cost', 'I paid for repairs', 'EXPENSE', 'Expense on Repairs', 'Cash A/C'],
]);

it('seeds each template with its words, its accounts, and no farm unit or category', function (string $slug, string $name, string $type, string $debit, string $credit) {
    $template = TransactionTemplate::where('slug', $slug)->firstOrFail();

    expect([$template->name, $template->transaction_type, $template->debitAccount->name, $template->creditAccount->name])->toBe([$name, $type, $debit, $credit])
        ->and($template->requires_farm_unit)->toBeFalse()
        ->and($template->is_produce_sale)->toBeFalse()
        ->and($template->farm_type_category_id)->toBeNull()
        ->and($template->is_system)->toBeTrue();
})->with('everyday');

it('can be seeded again without making copies', function () {
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(TransactionTemplateSeeder::class);

    expect(TransactionTemplate::where('slug', 'grant_income')->count())->toBe(1);
});
