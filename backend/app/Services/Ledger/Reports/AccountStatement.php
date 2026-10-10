<?php

namespace App\Services\Ledger\Reports;

use App\Enums\MoneyClass;
use Illuminate\Support\Carbon;

class AccountStatement
{
    public readonly int $totalInMinor;

    public readonly int $totalOutMinor;

    public readonly int $totalAssetsMinor;

    public readonly int $totalExpenditureMinor;

    public readonly int $totalIncomeMinor;

    public readonly int $totalLiabilityMinor;

    public readonly int $totalLoanRepaymentMinor;

    public readonly int $nonCashMinor;

    public readonly int $cancelledInMinor;

    public readonly int $cancelledOutMinor;

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
        /** @var array<string, int> the one result of classTotals() that the page and the signed figures both read */
        array $classTotals,
        /** @var array{in: int, out: int, cancelled_in: int, cancelled_out: int} the one result of moneyTotals() that the page and the signed figures both read */
        array $moneyTotals,
        int $nonCashMinor,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ReportHeader $header,
    ) {
        $ordinary = array_filter($rows, fn($row) => $row->cancelState !== 'correction');

        $this->totalInMinor = $moneyTotals['in'];
        $this->totalOutMinor = $moneyTotals['out'];

        $this->totalAssetsMinor = $classTotals[MoneyClass::Asset->value];
        $this->totalExpenditureMinor = $classTotals[MoneyClass::Expenditure->value];
        $this->totalIncomeMinor = $classTotals[MoneyClass::Income->value];
        $this->totalLiabilityMinor = $classTotals[MoneyClass::Liability->value];
        $this->totalLoanRepaymentMinor = $classTotals[MoneyClass::LoanRepayment->value];
        $this->nonCashMinor = $nonCashMinor;

        $this->cancelledInMinor = $moneyTotals['cancelled_in'];
        $this->cancelledOutMinor = $moneyTotals['cancelled_out'];

        $this->closingBalanceMinor = $rows === []
            ? $openingBalanceMinor
            : $rows[array_key_last($rows)]->balanceMinor;

        $this->lastPage = $perPage > 0 ? (int) max(1, ceil($total / $perPage)) : 1;
    }

    // money in and out over every row except the correction rows; and, measured on the cancelled
    // records themselves (never on their correction rows), the money in and out that was cancelled
    /**
     * @param array<int, AccountStatementRow> $rows
     * @return array{in: int, out: int, cancelled_in: int, cancelled_out: int}
     */
    public static function moneyTotals(array $rows): array
    {
        $ordinary = array_filter($rows, fn($row) => $row->cancelState !== 'correction');
        $cancelled = array_filter($rows, fn($row) => $row->cancelState === 'cancelled');

        return [
            'in' => array_sum(array_map(fn($row) => $row->moneyInMinor, $ordinary)),
            'out' => array_sum(array_map(fn($row) => $row->moneyOutMinor, $ordinary)),
            'cancelled_in' => array_sum(array_map(fn($row) => $row->moneyInMinor, $cancelled)),
            'cancelled_out' => array_sum(array_map(fn($row) => $row->moneyOutMinor, $cancelled)),
        ];
    }

    // the amount of the live non-cash records: a cancelled one and its correction row count zero
    /** @param array<int, AccountStatementRow> $rows */
    public static function nonCashTotal(array $rows): int
    {
        return array_sum(array_map(
            fn($row) => in_array($row->cancelState, ['open', 'waiting'], true) ? $row->nonCashMinor : 0,
            $rows,
        ));
    }

    // money in plus money out per class, over every row except the correction rows - the
    // correction of a record is not a second record, so it adds nothing to a class
    /**
     * @param array<int, AccountStatementRow> $rows
     * @return array<string, int>
     */
    public static function classTotals(array $rows): array
    {
        $ordinary = array_filter($rows, fn($row) => $row->cancelState !== 'correction');

        return collect(MoneyClass::cases())->mapWithKeys(fn(MoneyClass $class) => [
            $class->value => array_sum(array_map(
                fn($row) => $row->moneyClass === $class ? $row->moneyInMinor + $row->moneyOutMinor : 0,
                $ordinary,
            )),
        ])->all();
    }
}
