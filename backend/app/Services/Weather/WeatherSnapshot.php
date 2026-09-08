<?php

namespace App\Services\Weather;

use Illuminate\Support\Carbon;

class WeatherSnapshot
{
    public function __construct(
        public readonly bool $available,
        public readonly ?string $condition = null,
        public readonly ?Carbon $generatedAt = null,
        public readonly ?int $weatherCode = null,
        public readonly ?float $temperatureMaxC = null,
    ) {}

    public static function unavailable(): self
    {
        return new self(available: false);
    }
}
