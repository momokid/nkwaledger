<?php

namespace Database\Seeders;

use App\Models\FarmTypeCategory;
use App\Models\LedgerAccount;
use App\Models\TransactionTemplate;
use Illuminate\Database\Seeder;

class TransactionTemplateSeeder extends Seeder
{
    // a null category means the sentence is true on every farm
    protected array $templates = [
        [
            'name' => 'I sold my farm produce',
            'slug' => 'produce_sale',
            'transaction_type' => 'INCOME',
            'debit_account' => 'Cash A/C',
            'credit_account' => 'Income on Sales',
            'settlement_side' => 'debit',
            'requires_farm_unit' => true,
            'is_produce_sale' => true,
            'category' => null,
        ],
        [
            'name' => 'Money came in from something else',
            'slug' => 'other_income',
            'transaction_type' => 'INCOME',
            'debit_account' => 'Cash A/C',
            'credit_account' => 'Other Income',
            'settlement_side' => 'debit',
            'requires_farm_unit' => false,
            'is_produce_sale' => false,
            'category' => null,
        ],
        [
            'name' => 'I paid someone to work',
            'slug' => 'labour_cost',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Expense on Labour',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => false,
            'is_produce_sale' => false,
            'category' => null,
        ],
        [
            'name' => 'I paid for transport',
            'slug' => 'transport_cost',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Expense on Transport',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => false,
            'is_produce_sale' => false,
            'category' => null,
        ],
        [
            'name' => 'I bought something for the farm',
            'slug' => 'input_purchase',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Expense on Farm Input',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => null,
        ],

        // buying stock is something owned, not money spent, so the asset account is debited
        [
            'name' => 'I bought animals',
            'slug' => 'animal_purchase',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Livestock A/C',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => 'Livestock',
        ],
        [
            'name' => 'I bought feed',
            'slug' => 'feed_purchase',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Expense on Feed',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => 'Livestock',
        ],
        [
            'name' => 'I bought medicine or a vet',
            'slug' => 'vet_cost',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Expense on Medicine',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => 'Livestock',
        ],
        [
            'name' => 'I sold animals',
            'slug' => 'animal_sale',
            'transaction_type' => 'INCOME',
            'debit_account' => 'Cash A/C',
            'credit_account' => 'Income on Sales',
            'settlement_side' => 'debit',
            'requires_farm_unit' => true,
            'is_produce_sale' => true,
            'category' => 'Livestock',
        ],
        [
            'name' => 'I sold eggs or milk',
            'slug' => 'produce_of_animal_sale',
            'transaction_type' => 'INCOME',
            'debit_account' => 'Cash A/C',
            'credit_account' => 'Income on Sales',
            'settlement_side' => 'debit',
            'requires_farm_unit' => true,
            'is_produce_sale' => true,
            'category' => 'Livestock',
        ],
        // value gone, not money paid, so nothing settles anywhere
        [
            'name' => 'An animal died',
            'slug' => 'animal_loss',
            'transaction_type' => 'LOSS',
            'debit_account' => 'Loss on Farm Assets',
            'credit_account' => 'Livestock A/C',
            'settlement_side' => 'none',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => 'Livestock',
        ],

        [
            'name' => 'I bought seed',
            'slug' => 'seed_purchase',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Expense on Farm Input',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => 'Crop',
        ],
        [
            'name' => 'I bought fertiliser or spray',
            'slug' => 'fertiliser_purchase',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Expense on Farm Input',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => 'Crop',
        ],
        [
            'name' => 'I lost all my farm produce',
            'slug' => 'crop_loss',
            'transaction_type' => 'LOSS',
            'debit_account' => 'Loss on Farm Assets',
            'credit_account' => 'Crops in Field A/C',
            'settlement_side' => 'none',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => 'Crop',
        ],

        [
            'name' => 'I bought fingerlings',
            'slug' => 'fingerling_purchase',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Fish Stock A/C',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => 'Aquatic',
        ],
        [
            'name' => 'I bought fish feed',
            'slug' => 'fish_feed_purchase',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Expense on Feed',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => 'Aquatic',
        ],
        [
            'name' => 'I sold my fish',
            'slug' => 'fish_sale',
            'transaction_type' => 'INCOME',
            'debit_account' => 'Cash A/C',
            'credit_account' => 'Income on Sales',
            'settlement_side' => 'debit',
            'requires_farm_unit' => true,
            'is_produce_sale' => true,
            'category' => 'Aquatic',
        ],
        [
            'name' => 'My fish died',
            'slug' => 'fish_loss',
            'transaction_type' => 'LOSS',
            'debit_account' => 'Loss on Farm Assets',
            'credit_account' => 'Fish Stock A/C',
            'settlement_side' => 'none',
            'requires_farm_unit' => true,
            'is_produce_sale' => false,
            'category' => 'Aquatic',
        ],

        // every cancellation hangs off this one, and its lines are copied from the record it undoes
        [
            'name' => 'Correction of an earlier record',
            'slug' => 'correction',
            'transaction_type' => 'ADJUSTMENT',
            'debit_account' => 'Cash A/C',
            'credit_account' => 'Income on Sales',
            'settlement_side' => 'none',
            'requires_farm_unit' => false,
            'is_produce_sale' => false,
            'category' => null,
        ],
    ];

    public function run(): void
    {
        $accounts = LedgerAccount::pluck('id', 'name');
        $categories = FarmTypeCategory::pluck('id', 'name');

        foreach ($this->templates as $template) {
            $debitId = $accounts[$template['debit_account']] ?? null;
            $creditId = $accounts[$template['credit_account']] ?? null;

            // a renamed or removed account skips its template rather than failing the deploy
            if ($debitId === null || $creditId === null) {
                continue;
            }

            $categoryId = null;

            if ($template['category'] !== null) {
                $categoryId = $categories[$template['category']] ?? null;

                // a kind of farming nobody has set up yet skips its templates too
                if ($categoryId === null) {
                    continue;
                }
            }

            TransactionTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                [
                    'name' => $template['name'],
                    'transaction_type' => $template['transaction_type'],
                    'debit_account_id' => $debitId,
                    'credit_account_id' => $creditId,
                    'settlement_side' => $template['settlement_side'],
                    'requires_farm_unit' => $template['requires_farm_unit'],
                    'is_produce_sale' => $template['is_produce_sale'],
                    'farm_type_category_id' => $categoryId,
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
