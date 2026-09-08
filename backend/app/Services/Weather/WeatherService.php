<?php

namespace App\Services\Weather;

use App\Models\Community;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherService
{
    private const HEAVY_RAIN_MM = 20;
    private const STRONG_WIND_KMH = 40;
    private const VERY_HOT_C = 34;
    private const CACHE_MINUTES = 180;

    public function forCommunity(Community $community): WeatherSnapshot
    {
        $this->ensureCoordinates($community);

        if ($community->latitude === null || $community->longitude === null) {
            return WeatherSnapshot::unavailable();
        }

        $daily = $this->forecastFor((float) $community->latitude, (float) $community->longitude, $community->id);

        if ($daily === null) {
            return WeatherSnapshot::unavailable();
        }

        return new WeatherSnapshot(
            available: true,
            condition: $this->classify($daily),
            generatedAt: now(),
            weatherCode: isset($daily['weathercode'][0]) ? (int) $daily['weathercode'][0] : null,
            temperatureMaxC: isset($daily['temperature_2m_max'][0]) ? (float) $daily['temperature_2m_max'][0] : null,
        );
    }

    // day-by-day forecast for a dedicated weather page — separate from forCommunity()'s
    // single rolled-up condition, and cached under its own key since the day count differs
    public function extendedForecastFor(Community $community, int $days = 7): ?array
    {
        $this->ensureCoordinates($community);

        if ($community->latitude === null || $community->longitude === null) {
            return null;
        }

        $daily = $this->forecastFor((float) $community->latitude, (float) $community->longitude, $community->id, $days);

        if ($daily === null) {
            return null;
        }

        $dates = $daily['time'] ?? [];
        $rain = $daily['precipitation_sum'] ?? [];
        $tempMax = $daily['temperature_2m_max'] ?? [];
        $wind = $daily['windspeed_10m_max'] ?? [];
        $weatherCodes = $daily['weathercode'] ?? [];

        return collect($dates)
            ->map(fn($date, $i) => [
                'date' => $date,
                'condition' => $this->classifyDay(
                    (float) ($rain[$i] ?? 0),
                    (float) ($wind[$i] ?? 0),
                    (float) ($tempMax[$i] ?? 0),
                ),
                'precipitation_mm' => $rain[$i] ?? null,
                'temperature_max_c' => $tempMax[$i] ?? null,
                'windspeed_max_kmh' => $wind[$i] ?? null,
                'weather_code' => isset($weatherCodes[$i]) ? (int) $weatherCodes[$i] : null,
            ])
            ->values()
            ->all();
    }

    // geocoded once, then cached forever on the row, so no community is looked up twice;
    // public so the backfill command can call it directly for communities added before this feature existed
    public function ensureCoordinates(Community $community): void
    {
        if ($community->latitude !== null && $community->longitude !== null) {
            return;
        }

        // Open-Meteo's own docs: append at most ONE qualifier after the name —
        // a country or a first-level administrative area (region). The district
        // is a second-level division and stacking it in breaks real matches.
        $query = collect([
            $community->name,
            $community->district?->region?->name,
        ])->filter()->implode(', ');

        $result = $this->geocode($query);

        if ($result === null) {
            return;
        }

        $community->forceFill($result)->save();
    }

    // the one place that actually calls the geocoding API — reused by ensureCoordinates()
    // above and by the admin's "suggest a location" lookup, so there is only one HTTP call to get right
    public function geocode(string $query): ?array
    {
        $response = Http::get('https://geocoding-api.open-meteo.com/v1/search', [
            'name' => $query,
            'count' => 1,
            'language' => 'en',
            'format' => 'json',
            'countryCode' => 'GH',
        ]);

        if ($response->failed()) {
            return null;
        }

        $result = $response->json('results.0');

        if ($result === null) {
            return null;
        }

        return [
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
        ];
    }

    // several communities can share a name, so the admin gets a short labelled
    // list to pick from, rather than one silent guess
    public function geocodeCandidates(string $query, int $limit = 5): array
    {
        $response = Http::get('https://geocoding-api.open-meteo.com/v1/search', [
            'name' => $query,
            'count' => $limit,
            'language' => 'en',
            'format' => 'json',
            'countryCode' => 'GH',
        ]);

        if ($response->failed()) {
            return [];
        }

        $results = $response->json('results') ?? [];

        return collect($results)
            ->map(fn(array $result) => [
                'latitude' => $result['latitude'],
                'longitude' => $result['longitude'],
                'label' => collect([$result['name'] ?? null, $result['admin1'] ?? null])
                    ->filter()
                    ->implode(', '),
            ])
            ->values()
            ->all();
    }

    private function forecastFor(float $latitude, float $longitude, int $communityId, int $days = 3): ?array
    {
        return Cache::remember(
            "weather.forecast.{$communityId}.{$days}",
            now()->addMinutes(self::CACHE_MINUTES),
            function () use ($latitude, $longitude, $days) {
                try {
                    $response = Http::timeout(5)->get('https://api.open-meteo.com/v1/forecast', [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'daily' => 'precipitation_sum,temperature_2m_max,windspeed_10m_max,weathercode',
                        'timezone' => 'auto',
                        'forecast_days' => $days,
                    ]);
                } catch (\Illuminate\Http\Client\ConnectionException) {
                    return null;
                }

                if ($response->failed()) {
                    return null;
                }

                return $response->json('daily');
            },
        );
    }

    // rain outranks wind and heat, since it compounds into flooding and disease risk fastest
    private function classify(array $daily): string
    {
        if ($this->any($daily['precipitation_sum'] ?? [], self::HEAVY_RAIN_MM)) {
            return 'heavy_rain';
        }

        if ($this->any($daily['windspeed_10m_max'] ?? [], self::STRONG_WIND_KMH)) {
            return 'strong_wind';
        }

        if ($this->any($daily['temperature_2m_max'] ?? [], self::VERY_HOT_C)) {
            return 'very_hot';
        }

        return 'normal';
    }

    // same thresholds as classify(), but for exactly one day's readings — used to
    // label each day in the extended forecast rather than the window as a whole
    private function classifyDay(float $rain, float $wind, float $temp): string
    {
        if ($rain >= self::HEAVY_RAIN_MM) {
            return 'heavy_rain';
        }

        if ($wind >= self::STRONG_WIND_KMH) {
            return 'strong_wind';
        }

        if ($temp >= self::VERY_HOT_C) {
            return 'very_hot';
        }

        return 'normal';
    }

    private function any(array $values, float $threshold): bool
    {
        foreach ($values as $value) {
            if ((float) $value >= $threshold) {
                return true;
            }
        }

        return false;
    }
}
