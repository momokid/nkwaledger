<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use Illuminate\Database\Seeder;

class LedgerAccountSeeder extends Seeder
{
    // category name, then the side it grows on
    protected array $categories = [
        'Assets' => 'Dr',
        'Expenses' => 'Dr',
        'Income' => 'Cr',
        'Liabilities' => 'Cr',
        'Equity' => 'Cr',
    ];

    protected array $subcategories = [
        'Money' => 'Assets',
        'Farm Assets' => 'Assets',
        'Farm Income' => 'Income',
        'Farm Expenses' => 'Expenses',
        'Borrowings' => 'Liabilities',
        'Owner Funds' => 'Equity',
    ];

    protected array $accounts = [
        ['1001', 'Cash A/C', 'Money'],
        ['1002', 'Momo A/C', 'Money'],
        ['1003', 'Bank A/C', 'Money'],

        ['1201', 'Livestock A/C', 'Farm Assets'],
        ['1202', 'Crops in Field A/C', 'Farm Assets'],
        ['1203', 'Harvested Produce A/C', 'Farm Assets'],

        ['4001', 'Income on Sales', 'Farm Income'],
        ['4002', 'Other Income', 'Farm Income'],

        ['5001', 'Expense on Farm Input', 'Farm Expenses'],
        ['5002', 'Expense on Feed', 'Farm Expenses'],
        ['5003', 'Expense on Medicine', 'Farm Expenses'],
        ['5004', 'Expense on Labour', 'Farm Expenses'],
        ['5005', 'Expense on Transport', 'Farm Expenses'],
        ['5009', 'Loss on Farm Assets', 'Farm Expenses'],

        ['2001', 'Loan Payable', 'Borrowings'],
        ['3001', 'Stated Capital', 'Owner Funds'],
    ];

    public function run(): void
    {
        $classes = [];

        foreach (['Dr', 'Cr'] as $name) {
            $classes[$name] = LedgerClass::firstOrCreate(['name' => $name])->id;
        }

        $categories = [];

        foreach ($this->categories as $name => $side) {
            $categories[$name] = LedgerCategory::updateOrCreate(
                ['name' => $name],
                ['class_id' => $classes[$side]],
            )->id;
        }

        $subcategories = [];

        foreach ($this->subcategories as $name => $category) {
            $subcategories[$name] = LedgerSubcategory::updateOrCreate(
                ['name' => $name, 'category_id' => $categories[$category]],
            )->id;
        }

        $control = LedgerControl::firstOrCreate(['name' => 'General'])->id;
        $type = LedgerType::firstOrCreate(['name' => 'GL'])->id;

        foreach ($this->accounts as [$code, $name, $subcategory]) {
            LedgerAccount::updateOrCreate(
                ['name' => $name],
                [
                    'account_code' => $code,
                    'control_id' => $control,
                    'subcategory_id' => $subcategories[$subcategory],
                    'type_id' => $type,
                    // the platform's own accounts, not something an admin should remove
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
