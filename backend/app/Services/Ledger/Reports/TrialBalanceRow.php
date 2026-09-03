<?php

namespace App\Services\Ledger\Reports;

class TrialBalanceRow
{
    // what is left on the side this account naturally sits on
    public readonly int $balanceMinor;

    public function __construct(
        public readonly int $accountId,
        public readonly string $accountName,
        public readonly ?string $accountCode,
        // Dr or Cr, walked live from subcategory to category to class
        public readonly ?string $class,
        public readonly int $debitMinor,
        public readonly int $creditMinor,
    ) {
        $this->balanceMinor = $class === 'Cr'
            ? $creditMinor - $debitMinor
            : $debitMinor - $creditMinor;
    }
}
