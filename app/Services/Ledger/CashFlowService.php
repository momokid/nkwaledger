<?php

namespace App\Services\Ledger;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CashFlowService
{
    /**
     * Calculate cash flow for a user within a period.
     */
    public function calculate(
        int $userId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        $cashAccountId = DB::table('accounts')
            ->where('code', 'CASH')
            ->value('id');

        if (!$cashAccountId) {
            throw new \DomainException('Cash account not found');
        }

        $from = $fromDate ? Carbon::parse($fromDate) : null;
        $to   = $toDate ? Carbon::parse($toDate) : null;

        //  Opening balance (before period)
        $opening = $this->cashBalanceBefore($cashAccountId, $userId, $from);

        // 2️ Cash IN during period
        $cashIn = $this->sumEntries(
            $cashAccountId,
            $userId,
            'debit',
            $from,
            $to
        );

        // 3️ Cash OUT during period
        $cashOut = $this->sumEntries(
            $cashAccountId,
            $userId,
            'credit',
            $from,
            $to
        );

        return [
            'opening_cash' => round($opening, 2),
            'cash_in'      => round($cashIn, 2),
            'cash_out'     => round($cashOut, 2),
            'closing_cash' => round($opening + $cashIn - $cashOut, 2),
        ];
    }

    /**
     * Cash balance before a date.
     */
    private function cashBalanceBefore(
        int $accountId,
        int $userId,
        ?Carbon $beforeDate
    ): float {

        if (!$beforeDate) {
            return 0.0;
        }

        $totals = DB::table('ledger_entries')
            ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
            ->where('ledger_entries.account_id', $accountId)
            ->where('ledger_transactions.user_id', $userId)
            ->where('ledger_transactions.status', 'approved')
            ->where('ledger_transactions.transaction_date', '<', $beforeDate)
            ->selectRaw("
                SUM(CASE WHEN ledger_entries.entry_type = 'debit' THEN ledger_entries.amount ELSE 0 END) AS debits,
                SUM(CASE WHEN ledger_entries.entry_type = 'credit' THEN ledger_entries.amount ELSE 0 END) AS credits
            ")
            ->first();

        return ($totals->debits ?? 0) - ($totals->credits ?? 0);
    }

    /**
     * Sum cash entries within period.
     */
    private function sumEntries(
        int $accountId,
        int $userId,
        string $entryType,
        ?Carbon $from,
        ?Carbon $to
    ): float {

        $query = DB::table('ledger_entries')
            ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
            ->where('ledger_entries.account_id', $accountId)
            ->where('ledger_entries.entry_type', $entryType)
            ->where('ledger_transactions.user_id', $userId)
            ->where('ledger_transactions.status', 'approved');

        if ($from) {
            $query->where('ledger_transactions.transaction_date', '>=', $from);
        }

        if ($to) {
            $query->where('ledger_transactions.transaction_date', '<=', $to);
        }

        return (float) $query->sum('ledger_entries.amount');
    }
}
