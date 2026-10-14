<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use App\Models\TransactionTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// the two LOAN templates the Loans page posts through. Unlike the main template seeder this one never
// skips quietly: a missing account is a broken deploy and must be seen
class LoanTemplateSeeder extends Seeder
{
    protected array $templates = [
        [
            'name' => 'Loan received',
            'slug' => 'loan_received',
            // the account the farmer picks (Cash, MoMo or Bank) replaces this one
            'debit_account' => 'Cash A/C',
            'credit_account' => 'Loan Payable',
            'settlement_side' => 'debit',
            'is_liability' => true,
            'is_non_cash' => false,
        ],
        [
            'name' => 'Loan already had',
            'slug' => 'loan_already_had',
            'debit_account' => 'Stated Capital',
            'credit_account' => 'Loan Payable',
            'settlement_side' => 'none',
            'is_liability' => false,
            'is_non_cash' => true,
        ],
    ];

    public function run(): void
    {
        $accounts = LedgerAccount::pluck('id', 'name');

        $missing = collect($this->templates)
            ->flatMap(fn(array $template) => [$template['debit_account'], $template['credit_account']])
            ->unique()
            ->reject(fn(string $name) => isset($accounts[$name]))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException('The loan templates need these ledger accounts, which do not exist: ' . $missing->implode(', ') . '. Seed the ledger accounts first.');
        }

        DB::transaction(function () use ($accounts) {
            foreach ($this->templates as $template) {
                TransactionTemplate::updateOrCreate(
                    ['slug' => $template['slug']],
                    [
                        'name' => $template['name'],
                        'transaction_type' => 'LOAN',
                        'debit_account_id' => $accounts[$template['debit_account']],
                        'credit_account_id' => $accounts[$template['credit_account']],
                        'settlement_side' => $template['settlement_side'],
                        'requires_farm_unit' => false,
                        'is_liability' => $template['is_liability'],
                        'is_non_cash' => $template['is_non_cash'],
                        'farm_type_category_id' => null,
                        'is_system' => true,
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}
