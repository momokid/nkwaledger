<?php

namespace App\Services\Ledger;

use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use DomainException;

class LedgerTransactionService
{
    public function __construct(
        private LedgerPostingService $postingService
    ) {}

    /**
     * Record a transaction intent and post it if eligible.
     */
    public function create(
        int $userId,
        string $type,
        float $amount,
        string $transactionDate
    ): LedgerTransaction {

        if ($amount <= 0) {
            throw new DomainException('Transaction amount must be greater than zero.');
        }

        $date = Carbon::parse($transactionDate);

        return DB::transaction(function () use (
            $userId,
            $type,
            $amount,
            $date
        ): LedgerTransaction {

            // 1. Create transaction (voucher)
            $transaction = LedgerTransaction::create([
                'user_id'          => $userId,
                'type'             => $type,
                'amount'           => $amount,
                'transaction_date' => $date->toDateString(),
                'status'           => 'pending',
            ]);

            // 2. Attempt posting (auto-approval handled internally)
            $this->postingService->post($transaction);

            return $transaction;
        });
    }
}
