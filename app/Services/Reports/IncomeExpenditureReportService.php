<?php


namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class IncomeExpenditureReportService
{
    /**
     * Get income vs expense summary.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @param int|null $userId Optional farmer filter
     */

    public function getSummary(string $startDate, string $endDate, ?int $userId = null): array
    {
        $baseQuery = DB::query("ledger_entries")->join("ledger_transactions", "ledger_entries.ledger_trasnaction_id", "=", "ledger_transactions.id")->join("accounts", "ledger_entries.account_id", "=", "accounts.id")->where("ledger_transactions.status", "approved")->where("ledger_transactions.status", "approved")->whereBetween("ledger_transactions.transaction_date", [$startDate, $endDate]);

        if ($userId) {
            $baseQuery->where("ledger_transactions.user_id", $userId);
        }

        //total income (credits to income accounts)
        $totalIncome = (clone $baseQuery)->where("accounts.type", "income")->where("ledger_entries.entry_type", "credit")->sum("ledger_entries.amount");


        $totalExpenditure = (clone $baseQuery)->where("accounts.type", "expense")->where("ledger_entries.entry_type", "debit")->sum("ledger_entries.amount");

        return [
            "income" => (float) $totalIncome,
            "expenditure" => (float) $totalExpenditure,
            "net" => (float) ($totalIncome - $totalExpenditure)
        ];
    }
}
