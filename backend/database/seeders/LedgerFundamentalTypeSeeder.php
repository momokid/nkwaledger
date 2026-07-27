<?php

namespace Database\Seeders;

use App\Models\LedgerFundamentalType;
use Illuminate\Database\Seeder;

class LedgerFundamentalTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (LedgerFundamentalType::NAMES as $name) {
            LedgerFundamentalType::firstOrCreate(
                ['name' => $name],
                ['normal_balance' => LedgerFundamentalType::NORMAL_BALANCE_MAP[$name]]
            );
        }
    }
}
