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

    // Open-Meteo's own WMO weather code table — this is real API data,
    // not an invented classification like the rain/wind/heat thresholds above
    private const WMO_DESCRIPTIONS = [
        0 => 'Clear sky',
        1 => 'Mainly clear',
        2 => 'Partly cloudy',
        3 => 'Overcast',
        45 => 'Fog',
        48 => 'Depositing rime fog',
        51 => 'Light drizzle',
        53 => 'Moderate drizzle',
        55 => 'Dense drizzle',
        56 => 'Light freezing drizzle',
        57 => 'Dense freezing drizzle',
        61 => 'Slight rain',
        63 => 'Moderate rain',
        65 => 'Heavy rain',
        66 => 'Light freezing rain',
        67 => 'Heavy freezing rain',
        71 => 'Slight snow fall',
        73 => 'Moderate snow fall',
        75 => 'Heavy snow fall',
        77 => 'Snow grains',
        80 => 'Slight rain showers',
        81 => 'Moderate rain showers',
        82 => 'Violent rain showers',
        85 => 'Slight snow showers',
        86 => 'Heavy snow showers',
        95 => 'Thunderstorm',
        96 => 'Thunderstorm with slight hail',
        99 => 'Thunderstorm with heavy hail',
    ];

    private const WMO_FALLBACK = 'Weather conditions vary';

    // narrower than ADVICE above — only added where a specific crop or animal
    // genuinely reacts differently to a condition; anything else falls back to
    // the category-level advice, which stays honest rather than inventing detail
    private const ALERTS = [
        'heavy_rain' => [
            'Maize' => 'Waterlogged soil raises the risk of leaf blight and stalk rot in maize. Check drainage in low-lying parts of the field.',
            'Tomato' => 'Wet conditions increase the risk of blight in tomatoes. Watch for dark spots on leaves and remove affected plants early.',
            'Pepper' => 'Wet conditions increase the risk of blight in pepper plants. Watch for dark spots on leaves and remove affected plants early.',
            'Cassava' => 'Prolonged waterlogging can rot cassava tubers underground. Check drainage, especially on flat or low ground.',
            'Cocoa' => 'Wet, humid conditions raise the risk of black pod disease in cocoa. Inspect pods regularly and remove any infected ones.',
            'Layers' => 'Damp coops raise the risk of respiratory illness in layers. Keep bedding dry and ventilation good.',
            'Broilers' => 'Damp conditions raise the risk of illness in broilers. Keep bedding dry and ventilation good.',
        ],
        'very_hot' => [
            'Layers' => 'Extreme heat can reduce egg-laying in layers. Ensure constant access to cool water and shade.',
            'Broilers' => 'Extreme heat slows growth and raises stress in broilers. Ensure constant access to cool water and shade.',
            'Cattle' => 'Extreme heat raises the risk of heat stress in cattle. Ensure shade and constant access to water.',
            'Goat' => 'Extreme heat raises the risk of heat stress in goats. Ensure shade and constant access to water.',
            'Sheep' => 'Extreme heat raises the risk of heat stress in sheep. Ensure shade and constant access to water.',
            'Pig' => 'Extreme heat raises the risk of heat stress in pigs. Ensure shade, water, and a way to cool off such as mud or a wet area.',
            'Tilapia' => 'High temperatures can lower oxygen levels in ponds, stressing tilapia. Watch for fish gasping at the surface.',
            'Catfish' => 'High temperatures can lower oxygen levels in ponds, stressing catfish. Watch for fish gasping at the surface.',
            'Tomato' => 'Extreme heat can cause tomato flowers to drop before fruiting. Provide shade cloth if possible.',
            'Pepper' => 'Extreme heat can cause pepper flowers to drop before fruiting. Provide shade cloth if possible.',
            'Maize' => 'Extreme heat during flowering can reduce maize pollination and yield.',
        ],
        'strong_wind' => [
            'Plantain' => 'Strong winds can topple plantain plants or tear leaves. Check young plants for support.',
            'Cocoa' => 'Strong winds can break cocoa branches. Inspect trees after the wind passes.',
            'Layers' => 'Strong winds can damage coop roofing. Check that structures are secure.',
            'Broilers' => 'Strong winds can damage coop roofing. Check that structures are secure.',
        ],
    ];

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

    public function describeDay(int $weatherCode, float $tempMax): string
    {
        $description = self::WMO_DESCRIPTIONS[$weatherCode] ?? self::WMO_FALLBACK;

        return sprintf('%s, around %d°C.', $description, round($tempMax));
    }

    // a plain-English summary of the week ahead, built from the same per-day
    // conditions already shown in the forecast strip — no new data source needed
    public function outlookFor(array $days): string
    {
        $total = count($days);

        $counts = [
            'heavy_rain' => 0,
            'strong_wind' => 0,
            'very_hot' => 0,
        ];

        foreach ($days as $day) {
            $condition = $day['condition'] ?? 'normal';
            if (isset($counts[$condition])) {
                $counts[$condition]++;
            }
        }

        $labels = [
            'heavy_rain' => 'Rain expected on %d of the next %d days',
            'strong_wind' => 'strong winds expected on %d of the next %d days',
            'very_hot' => 'very hot conditions expected on %d of the next %d days',
        ];

        $phrases = [];
        foreach ($labels as $condition => $template) {
            if ($counts[$condition] > 0) {
                $phrases[] = sprintf($template, $counts[$condition], $total);
            }
        }

        if (empty($phrases)) {
            return "No significant weather risks expected over the next {$total} days.";
        }

        if (count($phrases) === 1) {
            return $phrases[0] . '.';
        }

        $last = array_pop($phrases);

        return implode(', ', $phrases) . ', and ' . $last . '.';
    }

    public function alertFor(string $condition, string $farmType): ?string
    {
        return self::ALERTS[$condition][$farmType] ?? null;
    }
}
