<?php

namespace App\Services\Ledger;

class PostingRequest
{
    public function __construct(
        public readonly int $farmerProfileId,
        public readonly int $transactionTemplateId,
        // exactly what the farmer typed, in cedis, still a string
        public readonly string|int|float $amount,
        public readonly ?int $settlementAccountId,
        public readonly string $transactionDate,
        public readonly ?int $farmUnitId = null,
        public readonly ?string $narration = null,
        public readonly string $channel = 'web',
        public readonly ?int $recordedBy = null,
        // the phone names the record before sending, so a retry posts once
        public readonly ?string $idempotencyKey = null,
    ) {}
}
