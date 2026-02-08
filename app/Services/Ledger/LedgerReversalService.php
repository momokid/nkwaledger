<?php

namespace App\Services\Ledger;

use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;

class LedgerReversalService
{
    public function reverse(
        LedgerTransaction $original,
        int $supervisorId,
        string $reason = null
    ): LedgerTransaction {
        if ($original->status !== 'approved') {
            throw new \DomainException('Only approved transactions can be reversed.');
        }

        return DB::transaction(function () use ($original, $supervisorId, $reason) {

            $reversal = LedgerTransaction::create([
                'user_id' => $original->user_id,
                'type' => $original->type,
                'amount' => $original->amount,
                'transaction_date' => now()->toDateString(),

                'status' => 'approved',
                'approved_by' => $supervisorId,
                'approved_at' => now(),

                'is_reversal' => true,
                'reverses_transaction_id' => $original->id,
            ]);

            // Post reversal entries
            app(LedgerPostingService::class)->post($reversal);

            return $reversal;
        });
    }
}
