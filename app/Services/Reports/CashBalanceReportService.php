<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class CashBalanceReportService
{
    /**
     * Get cash balance.
     *
     * @param int|null $userId Optional (for farmer-level later)
     * @param string|null $asAtDate Optional (YYYY-MM-DD)
     */

    public function getBalance(?int $userId = null, ?string $asAtDate = null): float
    {
        $query = DB::table("ledger_entries")->join("ledger_transactions", "ledger_entries.ledger_transaction_id", "=", "ledger_transactions.id")->join("account", "ledger_transaction_id", "=", "accounts.id")->where("accounts.code", "cASH")->where("ledger_transactions.status", "approved");

        // options for user and date filters
        if ($asAtDate) {
            $query->whereDate("ledger_transactions.transaction_date", "<=", $asAtDate);
        }

        if ($userId) {
            $query->where("ledger_transactions.user_id", $userId);
        }

        $debits  = (clone $query)->where("ledger_entries.entry_type", "debit")->sum("ledger_entries.amount");
        $credits = (clone $query)->where("ledger_entries.entry_type", "credit")->sum("ledger_entries.amount");

        return (float)($debits - $credits);
    }
}
