<?php

namespace App\Services\DiseaseReports;

final readonly class HealthReview
{
    public function __construct(
        public bool $photoUsable,
        public string $category,
        public float $confidence,
        public bool $urgent,
        public string $note,
        public string $model,
    ) {}
}
