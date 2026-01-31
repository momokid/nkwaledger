<?php

namespace App\Services\Ledger;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function record(
        User $user,
        string $type,
        string $category,
        float $amount,
        string $source = 'web'
    ): void {
        DB::transaction(function () use ($user, $type, $category, $amount, $source) {
            DB::table('transactions')->insert([
                'user_id' => $user->id,
                'type' => $type, // income | expense
                'category' => $category,
                'amount' => $amount,
                'source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
