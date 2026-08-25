<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use App\Models\TransactionTemplate;
use Illuminate\Database\Seeder;

class TransactionTemplateSeeder extends Seeder
{
    protected array $templates = [
        [
            'name' => 'I sold my produce',
            'slug' => 'produce_sale',
            'transaction_type' => 'INCOME',
            'debit_account' => 'Cash A/C',
            'credit_account' => 'Income on Sales',
            'settlement_side' => 'debit',
            'requires_farm_unit' => false,
        ],
        [
            'name' => 'I bought farm inputs',
            'slug' => 'input_purchase',
            'transaction_type' => 'EXPENSE',
            'debit_account' => 'Expense on Farm Input',
            'credit_account' => 'Cash A/C',
            'settlement_side' => 'credit',
            'requires_farm_unit' => true,
        ],
    ];

    public function run(): void
    {
        $accounts = LedgerAccount::pluck('id', 'name');

        foreach ($this->templates as $template) {
            $debitId = $accounts[$template['debit_account']] ?? null;
            $creditId = $accounts[$template['credit_account']] ?? null;

            // a renamed or removed account skips its template rather than failing the deploy
            if ($debitId === null || $creditId === null) {
                continue;
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
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
