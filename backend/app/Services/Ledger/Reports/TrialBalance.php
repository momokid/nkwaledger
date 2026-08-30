<?php

namespace App\Services\Ledger\Reports;

use Illuminate\Support\Carbon;

class TrialBalance
{
    public readonly int $totalDebitMinor;

    public readonly int $totalCreditMinor;

    public function __construct(
        public readonly int $farmerProfileId,
        public readonly string $from,
        public readonly string $to,
        // a report that cannot say what it left out is a report nobody can defend
        public readonly bool $includeProvisional,
        public readonly int $provisionalHeldBackMinor,
        public readonly Carbon $generatedAt,
        /** @var array<int, TrialBalanceRow> */
        public readonly array $rows,
        // the top and bottom every printed report carries
        public readonly ReportHeader $header,
    ) {
        $this->totalDebitMinor = array_sum(array_map(fn($row) => $row->debitMinor, $rows));
        $this->totalCreditMinor = array_sum(array_map(fn($row) => $row->creditMinor, $rows));
    }

    public function isBalanced(): bool
    {
        return $this->totalDebitMinor === $this->totalCreditMinor;
    }

    public function row(int $accountId): ?TrialBalanceRow
    {
        foreach ($this->rows as $row) {
            if ($row->accountId === $accountId) {
                return $row;
            }
        }

        return null;
    }
}
