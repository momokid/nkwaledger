<?php

namespace App\Services\Ledger;

use Illuminate\Support\Facades\DB;

class LedgerBalanceService
{
    /**
     * Get balance for a single account.
     */
    public function accountBalance(int $accountId, int $userId): float
    {
        $account = DB::table('accounts')->where('id', $accountId)->first();

        if (!$account) {
            throw new \DomainException('Account not found');
        }

        $totals = DB::table('ledger_entries')
            ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
            ->where('ledger_entries.account_id', $accountId)
            ->where('ledger_transactions.user_id', $userId)
            ->where('ledger_transactions.status', 'approved')
            ->selectRaw("
                SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END) AS debits,
                SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END) AS credits
            ")
            ->first();

        $debits  = $totals->debits ?? 0;
        $credits = $totals->credits ?? 0;

        return $this->calculateBalance($account->type, $debits, $credits);
    }

    /**
     * Get balances for all accounts of a user.
     */
    public function allBalances(int $userId): array
    {
        $accounts = DB::table('accounts')->get();
        $balances = [];

        foreach ($accounts as $account) {
            $balances[$account->code] = $this->accountBalance($account->id, $userId);
        }

        return $balances;
    }

    /**
     * Accounting rules enforcement.
     */
    private function calculateBalance(string $accountType, float $debits, float $credits): float
    {
        return match ($accountType) {
            'asset', 'expense' => $debits - $credits,
            'income', 'liability' => $credits - $debits,
            default => throw new \DomainException('Invalid account type'),
        };
    }
}
