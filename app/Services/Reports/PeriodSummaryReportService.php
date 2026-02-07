<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class PeriodSummaryReportService
{
    public function generate(
        string $startDate,
        string $endDate,
        ?int $userId = null
    ): array {

        $openingBalance = $this->openingBalance($startDate, $userId);

        $movement = $this->periodMovement(
            $startDate,
            $endDate,
            $userId
        );

        $closingBalance =
            $openingBalance
            + $movement['income']
            - $movement['expense'];

        return [
            'period' => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
            'opening_balance' => $openingBalance,
            'income'          => $movement['income'],
            'expense'         => $movement['expense'],
            'closing_balance' => $closingBalance,
        ];
    }

    /**
     * Opening balance = all approved cash movements before period start
     */
    private function openingBalance(
        string $startDate,
        ?int $userId
    ): float {

        $query = DB::table('ledger_entries')
            ->join(
                'ledger_transactions',
                'ledger_entries.ledger_transaction_id',
                '=',
                'ledger_transactions.id'
            )
            ->join(
                'accounts',
                'ledger_entries.account_id',
                '=',
                'accounts.id'
            )
            ->where('accounts.code', 'CASH')
            ->where('ledger_transactions.status', 'approved')
            ->where(
                'ledger_transactions.transaction_date',
                '<',
                $startDate
            );

        if ($userId) {
            $query->where(
                'ledger_transactions.user_id',
                $userId
            );
        }

        $debits = (clone $query)
            ->where('ledger_entries.entry_type', 'debit')
            ->sum('ledger_entries.amount');

        $credits = (clone $query)
            ->where('ledger_entries.entry_type', 'credit')
            ->sum('ledger_entries.amount');

        return (float) ($debits - $credits);
    }

    /**
     * Income and expense within the period
     */
    private function periodMovement(
        string $startDate,
        string $endDate,
        ?int $userId
    ): array {

        $query = DB::table('ledger_entries')
            ->join(
                'ledger_transactions',
                'ledger_entries.ledger_transaction_id',
                '=',
                'ledger_transactions.id'
            )
            ->join(
                'accounts',
                'ledger_entries.account_id',
                '=',
                'accounts.id'
            )
            ->where('ledger_transactions.status', 'approved')
            ->whereBetween(
                'ledger_transactions.transaction_date',
                [$startDate, $endDate]
            );

        if ($userId) {
            $query->where(
                'ledger_transactions.user_id',
                $userId
            );
        }

        $income = (clone $query)
            ->where('accounts.type', 'income')
            ->where('ledger_entries.entry_type', 'credit')
            ->sum('ledger_entries.amount');

        $expense = (clone $query)
            ->where('accounts.type', 'expense')
            ->where('ledger_entries.entry_type', 'debit')
            ->sum('ledger_entries.amount');

        return [
            'income'  => (float) $income,
            'expense' => (float) $expense,
        ];
    }
}
