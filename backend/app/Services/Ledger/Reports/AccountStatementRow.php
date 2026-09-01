<?php

namespace App\Services\Ledger\Reports;

class AccountStatementRow
{
    public function __construct(
        public readonly int $transactionId,
        public readonly string $uuid,
        // the number a farmer reads out on a phone call
        public readonly string $reference,
        public readonly string $transactionDate,
        public readonly string $transactionType,
        public readonly string $templateName,
        // the farmer's own words, or the template name if they wrote none
        public readonly string $description,
        public readonly int $moneyInMinor,
        public readonly int $moneyOutMinor,
        // the cash position after this row
        public readonly int $balanceMinor,
        public readonly bool $isProvisional,
        public readonly string $cancelState,
        public readonly ?string $accountName,
    ) {}
}
