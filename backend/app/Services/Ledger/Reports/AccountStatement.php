<?php

namespace App\Services\Ledger\Reports;

use Illuminate\Support\Carbon;

class AccountStatement
{
    public readonly int $totalInMinor;

    public readonly int $totalOutMinor;

    public readonly int $cancelledMinor;

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
        $ordinary = array_filter($rows, fn($row) => $row->cancelState !== 'correction');
        $corrections = array_filter($rows, fn($row) => $row->cancelState === 'correction');

        $this->totalInMinor = array_sum(array_map(fn($row) => $row->moneyInMinor, $ordinary));
        $this->totalOutMinor = array_sum(array_map(fn($row) => $row->moneyOutMinor, $ordinary));

        $this->cancelledMinor = array_sum(
            array_map(fn($row) => $row->moneyInMinor + $row->moneyOutMinor, $corrections),
        );

        $this->closingBalanceMinor = $rows === []
            ? $openingBalanceMinor
            : $rows[array_key_last($rows)]->balanceMinor;

        $this->lastPage = $perPage > 0 ? (int) max(1, ceil($total / $perPage)) : 1;
    }
}
