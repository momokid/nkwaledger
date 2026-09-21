<?php

namespace App\Services\Ledger;

class PostingRequest
{
    public function __construct(
        public readonly int $farmerProfileId,
        public readonly int $transactionTemplateId,
        public readonly string|int|float $amount,
        public readonly ?int $settlementAccountId,
        public readonly string $transactionDate,
        public readonly ?int $farmUnitId = null,
        public readonly ?string $narration = null,
        public readonly string $channel = 'web',
        public readonly ?int $recordedBy = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $quantityLost = null,
        public readonly ?string $quantitySold = null,
        public readonly ?string $quantityPurchased = null,
        // only used when a stock purchase starts a brand new batch; an addition to an
        // existing one keeps whatever unit/date it already had
        public readonly ?string $unitOfMeasure = null,
        public readonly ?string $expectedReadyOn = null,
    ) {}
}
