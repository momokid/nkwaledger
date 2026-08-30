<?php

namespace App\Services\Ledger\Reports;

use Illuminate\Support\Carbon;

class IncomeAndExpenditure
{
    public readonly int $totalIncomeMinor;

    public readonly int $totalExpenseMinor;

    public readonly int $totalLossMinor;

    // what is left after money paid out and value gone
    public readonly int $netMinor;

    public function __construct(
        public readonly int $farmerProfileId,
        public readonly string $from,
        public readonly string $to,
        public readonly bool $includeProvisional,
        public readonly int $provisionalHeldBackMinor,
        public readonly Carbon $generatedAt,
        /** @var array<int, IncomeLine> */
        public readonly array $incomeRows,
        /** @var array<int, IncomeLine> */
        public readonly array $expenseRows,
        /** @var array<int, IncomeLine> */
        public readonly array $lossRows,
    ) {
        $this->totalIncomeMinor = $this->sum($incomeRows);
        $this->totalExpenseMinor = $this->sum($expenseRows);
        $this->totalLossMinor = $this->sum($lossRows);

        $this->netMinor = $this->totalIncomeMinor - $this->totalExpenseMinor - $this->totalLossMinor;
    }

    /** @param array<int, IncomeLine> $rows */
    public function row(array $rows, int $accountId): ?IncomeLine
    {
        foreach ($rows as $row) {
            if ($row->accountId === $accountId) {
                return $row;
            }
        }

        return null;
    }

    /** @param array<int, IncomeLine> $rows */
    private function sum(array $rows): int
    {
        return array_sum(array_map(fn(IncomeLine $row) => $row->amountMinor, $rows));
    }
}
