<?php

namespace App\Services\Agent;

use App\Models\FarmerProfile;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use App\Services\Ledger\Reports\IncomeLine;

class AgentIncomeSummaryService
{
    public function __construct(
        private readonly IncomeAndExpenditureService $incomes,
    ) {}

    // pulls every assigned farmer's own income and expenditure report and merges
    // them by account, so "Sales A/C" across ten farmers becomes one line, not ten
    public function for(int $agentId, string $from, string $to): array
    {
        $farmerIds = FarmerProfile::query()
            ->where('assigned_agent_id', $agentId)
            ->pluck('id');

        $incomeByAccount = [];
        $expenseByAccount = [];
        $totalIncome = 0;
        $totalExpense = 0;

        foreach ($farmerIds as $farmerId) {
            $report = $this->incomes->for(farmerProfileId: $farmerId, from: $from, to: $to);

            $totalIncome += $report->totalIncomeMinor;
            $totalExpense += $report->totalExpenseMinor;

            $this->accumulate($incomeByAccount, $report->incomeRows);
            $this->accumulate($expenseByAccount, $report->expenseRows);
        }

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net' => $totalIncome - $totalExpense,
            'income_by_account' => $this->sorted($incomeByAccount),
            'expense_by_account' => $this->sorted($expenseByAccount),
        ];
    }

    /**
     * @param array<int, array{account: string, amount: int}> $accumulator
     * @param array<int, IncomeLine> $rows
     */
    private function accumulate(array &$accumulator, array $rows): void
    {
        foreach ($rows as $row) {
            $accumulator[$row->accountId] ??= ['account' => $row->accountName, 'amount' => 0];
            $accumulator[$row->accountId]['amount'] += $row->amountMinor;
        }
    }

    /** @param array<int, array{account: string, amount: int}> $accumulator */
    private function sorted(array $accumulator): array
    {
        $rows = array_values($accumulator);
        usort($rows, fn($a, $b) => $b['amount'] <=> $a['amount']);

        return $rows;
    }
}
