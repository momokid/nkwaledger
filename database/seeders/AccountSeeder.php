<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            // Assets
            ['code' => 'CASH', 'name' => 'Cash', 'type' => 'asset'],

            // Income
            ['code' => 'FARM_INCOME', 'name' => 'Farm Income', 'type' => 'income'],

            // Expenses
            ['code' => 'FARM_EXPENSE', 'name' => 'Farm Expenses', 'type' => 'expense'],

            // Losses
            ['code' => 'FARM_LOSS', 'name' => 'Farm Losses', 'type' => 'loss'],

            // Equity
            ['code' => 'OWNER_EQUITY', 'name' => 'Owner Equity', 'type' => 'equity'],
        ];

        foreach ($accounts as $account) {
            DB::table('accounts')->insert([
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
