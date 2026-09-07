<?php

namespace App\Services\Weather;

class WeatherAdvisor
{
    private const HEADLINES = [
        'heavy_rain' => 'Heavy rain expected',
        'strong_wind' => 'Strong winds expected',
        'very_hot' => 'Very hot weather expected',
        'normal' => 'No significant weather risks',
    ];

    // one message per category per condition; a category we don't recognise
    // still gets something sensible rather than breaking the page
    private const ADVICE = [
        'heavy_rain' => [
            'Crop' => 'Heavy rain is expected in the next few days. If your crops are close to harvest, consider harvesting early to avoid losses.',
            'Livestock' => 'Heavy rain is expected. Make sure animal shelters are dry and well-drained to prevent disease.',
            'Aquatic' => 'Heavy rain is expected. Check pond banks and drainage to prevent overflow or fish escaping.',
        ],
        'very_hot' => [
            'Crop' => 'Very hot weather is expected. Water your crops early morning or evening to reduce water loss.',
            'Livestock' => 'Very hot weather is expected. Ensure animals have shade and enough water to drink.',
            'Aquatic' => 'Very hot weather is expected. Watch pond oxygen levels — heat can lower oxygen for fish.',
        ],
        'strong_wind' => [
            'Crop' => 'Strong winds are expected. Secure any stakes, nets, or young plants that could be damaged.',
            'Livestock' => 'Strong winds are expected. Check that shelters and pens are secure.',
            'Aquatic' => 'Strong winds are expected. Secure pond covers and equipment.',
        ],
    ];

    private const NORMAL_ADVICE = 'No significant weather risks expected in the next few days.';

    private const FALLBACK_ADVICE = 'Keep an eye on the weather over the next few days and take care of your farm as usual.';

    public function headline(string $condition): string
    {
        return self::HEADLINES[$condition] ?? self::HEADLINES['normal'];
    }

    public function adviceFor(string $condition, string $category): string
    {
        if ($condition === 'normal') {
            return self::NORMAL_ADVICE;
        }

        return self::ADVICE[$condition][$category] ?? self::FALLBACK_ADVICE;
    }
}
