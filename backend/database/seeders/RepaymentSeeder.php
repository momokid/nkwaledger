<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\TransactionTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// the account and the two templates a loan repayment posts through. Safe to run again. Like the loan templates it
// never skips quietly: a missing account is a broken deploy and must be seen
class RepaymentSeeder extends Seeder
{
    public const INTEREST_ACCOUNT = 'Interest and Fees';

    public function run(): void
    {
        $accounts = LedgerAccount::pluck('id', 'name');
        $subcategory = LedgerSubcategory::where('name', 'Farm Expenses')->first();

        $missing = collect(['Loan Payable', 'Cash A/C'])->reject(fn(string $name) => isset($accounts[$name]))->values();

        if ($subcategory === null || $missing->isNotEmpty()) {
            throw new RuntimeException('The repayment templates need the Farm Expenses group and these ledger accounts: ' . ($missing->implode(', ') ?: 'none missing') . '. Seed the ledger accounts first.');
        }

        DB::transaction(function () use ($accounts, $subcategory) {
            $interest = LedgerAccount::updateOrCreate(
                ['name' => self::INTEREST_ACCOUNT],
                [
                    'account_code' => '5010',
                    'control_id' => LedgerControl::firstOrCreate(['name' => 'General'])->id,
                    'subcategory_id' => $subcategory->id,
                    'type_id' => LedgerType::firstOrCreate(['name' => 'GL'])->id,
                    'is_system' => true,
                    'is_settlement' => false,
                    'is_active' => true,
                ],
            );

            // the credit side is a stand-in: the account the farmer paid from replaces it
            $templates = [
                ['Loan repayment (principal)', 'loan_repayment_principal', 'LOAN', $accounts['Loan Payable']],
                ['Loan repayment (interest)', 'loan_repayment_interest', 'EXPENSE', $interest->id],
            ];

            foreach ($templates as [$name, $slug, $type, $debit]) {
                TransactionTemplate::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'transaction_type' => $type,
                        'debit_account_id' => $debit,
                        'credit_account_id' => $accounts['Cash A/C'],
                        'settlement_side' => 'credit',
                        'requires_farm_unit' => false,
                        'is_liability' => false,
                        'is_non_cash' => false,
                        'farm_type_category_id' => null,
                        'is_system' => true,
                        'is_active' => true,
                        'is_hidden' => true,
                    ]
                );
            }
        });
    }
}
