<?php

namespace App\Services\Ledger\Reports;

use Illuminate\Support\Carbon;

class AccountStatement
{
    public readonly int $totalInMinor;

    public readonly int $totalOutMinor;

    public readonly int $closingBalanceMinor;

    public readonly int $lastPage;

    public function __construct(
        public readonly int $farmerProfileId,
        public readonly string $from,
        public readonly string $to,
        public readonly ?int $accountId,
        public readonly bool $includeProvisional,
        public readonly int $provisionalHeldBackMinor,
        public readonly int $openingBalanceMinor,
        public readonly Carbon $generatedAt,
        /** @var array<int, AccountStatementRow> */
        public readonly array $rows,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ReportHeader $header,
    ) {
        $this->totalInMinor = array_sum(array_map(fn($row) => $row->moneyInMinor, $rows));
        $this->totalOutMinor = array_sum(array_map(fn($row) => $row->moneyOutMinor, $rows));

        $this->closingBalanceMinor = $rows === []
            ? $openingBalanceMinor
            : $rows[array_key_last($rows)]->balanceMinor;

        $this->lastPage = $perPage > 0 ? (int) max(1, ceil($total / $perPage)) : 1;
    }
}
