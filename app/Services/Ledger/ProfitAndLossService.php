<?php

namespace App\Services\Ledger;

use Illuminate\Support\Facades\DB;

class ProfitAndLossService
{
    public function calculate(int $userId): array
    {
        // Get totals grouped by account type
        $rows = DB::table('ledger_entries')
            ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
            ->join('accounts', 'ledger_entries.account_id', '=', 'accounts.id')
            ->where('ledger_transactions.user_id', $userId)
            ->where('ledger_transactions.status', 'approved')
            ->selectRaw("
                accounts.type,
                SUM(
                    CASE 
                        WHEN ledger_entries.entry_type = 'credit' THEN ledger_entries.amount
                        ELSE -ledger_entries.amount
                    END
                ) as balance
            ")
            ->groupBy('accounts.type')
            ->get();

        $income  = 0;
        $expense = 0;
        $loss    = 0;

        foreach ($rows as $row) {
            match ($row->type) {
                'income'  => $income  += $row->balance,
                'expense' => $expense += abs($row->balance),
                default   => null,
            };
        }

        // Loss is treated as an expense category
        $loss = $this->lossTotal($userId);

        return [
            'total_income'  => round($income, 2),
            'total_expense' => round($expense, 2),
            'total_loss'    => round($loss, 2),
            'net_profit'    => round($income - ($expense + $loss), 2),
        ];
    }

    /**
     * Losses are tracked separately for clarity.
     */
    private function lossTotal(int $userId): float
    {
        return DB::table('ledger_entries')
            ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
            ->join('accounts', 'ledger_entries.account_id', '=', 'accounts.id')
            ->where('ledger_transactions.user_id', $userId)
            ->where('ledger_transactions.status', 'approved')
            ->where('accounts.type', 'loss')
            ->sum('ledger_entries.amount');
    }
}
