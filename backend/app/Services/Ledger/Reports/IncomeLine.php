<?php

namespace App\Services\Ledger\Reports;

class IncomeLine
{
    public function __construct(
        public readonly int $accountId,
        public readonly string $accountName,
        public readonly ?string $accountCode,
        // the group the screen folds this line into
        public readonly string $groupName,
        public readonly int $amountMinor,
    ) {}
}
