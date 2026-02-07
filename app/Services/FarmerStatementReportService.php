<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class FarmerStatementReportService
{
    public function generate(
        int $userId,
        string $startDate,
        string $endDate
    ): array {

        $openingBalance = $this->openingBalance(
            $userId,
            $startDate
        );

        $rows = DB::table('ledger_entries')
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
            ->where('ledger_transactions.user_id', $userId)
            ->where('ledger_transactions.status', 'approved')
            ->where('accounts.code', 'CASH')
            ->whereBetween(
                'ledger_transactions.transaction_date',
                [$startDate, $endDate]
            )
            ->orderBy('ledger_transactions.transaction_date')
            ->orderBy('ledger_entries.id')
            ->select([
                'ledger_transactions.transaction_date',
                'ledger_transactions.type',
                'ledger_entries.entry_type',
                'ledger_entries.amount',
            ])
            ->get();

        $balance = $openingBalance;
        $statement = [];

        foreach ($rows as $row) {
            $in  = 0.0;
            $out = 0.0;

            if ($row->entry_type === 'debit') {
                $in = $row->amount;
                $balance += $row->amount;
            } else {
                $out = $row->amount;
                $balance -= $row->amount;
            }

            $statement[] = [
                'date'        => $row->transaction_date,
                'description' => ucfirst($row->type),
                'in'          => $in,
                'out'         => $out,
                'balance'     => $balance,
            ];
        }

        return [
            'opening_balance' => $openingBalance,
            'statement'       => $statement,
            'closing_balance' => $balance,
        ];
    }

    private function openingBalance(
        int $userId,
        string $startDate
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
            ->where('ledger_transactions.user_id', $userId)
            ->where('ledger_transactions.status', 'approved')
            ->where('accounts.code', 'CASH')
            ->where(
                'ledger_transactions.transaction_date',
                '<',
                $startDate
            );

        $debits = (clone $query)
            ->where('ledger_entries.entry_type', 'debit')
            ->sum('ledger_entries.amount');

        $credits = (clone $query)
            ->where('ledger_entries.entry_type', 'credit')
            ->sum('ledger_entries.amount');

        return (float) ($debits - $credits);
    }
}
