<?php

use App\Models\Community;
use App\Services\Weather\WeatherService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = app(WeatherService::class);
});

function fakeGeocode(?array $result = ['latitude' => 6.7000, 'longitude' => -1.5000]): void
{
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(
            $result === null ? ['results' => []] : ['results' => [$result]],
        ),
    ]);
}

function fakeForecast(array $daily): void
{
    Http::fake([
        'api.open-meteo.com/*' => Http::response(['daily' => $daily]),
    ]);
}

function normalDaily(): array
{
    return [
        'precipitation_sum' => [2, 5, 3],
        'temperature_2m_max' => [28, 29, 27],
        'windspeed_10m_max' => [15, 18, 12],
    ];
}

test('geocode() returns coordinates for a plain query string', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 5.55, 'longitude' => -0.2]]]),
    ]);

    expect($this->service->geocode('Osu, Accra, Greater Accra, Ghana'))
        ->toBe(['latitude' => 5.55, 'longitude' => -0.2]);
});

test('geocode() returns null when nothing is found', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => []]),
    ]);

    expect($this->service->geocode('Nowhereville, Ghana'))->toBeNull();
});

test('geocode() returns null when the request fails', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response([], 500),
    ]);

    expect($this->service->geocode('Anything, Ghana'))->toBeNull();
});

test('geocodeCandidates() returns a labelled list of matches', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [
            ['name' => 'Anyinasu', 'admin1' => 'Ashanti', 'latitude' => 7.38, 'longitude' => -1.36],
            ['name' => 'Anyinasu', 'admin1' => 'Eastern', 'latitude' => 6.12, 'longitude' => -0.45],
        ]]),
    ]);

    $candidates = $this->service->geocodeCandidates('Anyinasu, Ghana');

    expect($candidates)->toHaveCount(2);
    expect($candidates[0])->toBe(['latitude' => 7.38, 'longitude' => -1.36, 'label' => 'Anyinasu, Ashanti']);
    expect($candidates[1]['label'])->toBe('Anyinasu, Eastern');
});

test('geocodeCandidates() falls back to just the name when no region is given', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [
            ['name' => 'Somewhere', 'latitude' => 1.0, 'longitude' => 2.0],
        ]]),
    ]);

    expect($this->service->geocodeCandidates('Somewhere')[0]['label'])->toBe('Somewhere');
});

test('geocodeCandidates() returns an empty list when nothing is found', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => []]),
    ]);

    expect($this->service->geocodeCandidates('Nowhereville'))->toBe([]);
});

test('geocodeCandidates() returns an empty list when the request fails', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response([], 500),
    ]);

    expect($this->service->geocodeCandidates('Anything'))->toBe([]);
});

test('geocodes a community with no coordinates and saves them', function () {
    $community = Community::factory()->create(['latitude' => null, 'longitude' => null]);

    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 6.7, 'longitude' => -1.5]]]),
        'api.open-meteo.com/*' => Http::response(['daily' => normalDaily()]),
    ]);

    $this->service->forCommunity($community);

    expect((float) $community->fresh()->latitude)->toBe(6.7);
    expect((float) $community->fresh()->longitude)->toBe(-1.5);
});

test('the geocoding query uses only the region as a qualifier, plus countryCode, not the district', function () {
    $region = \App\Models\Region::create(['name' => 'Ashanti']);
    $district = \App\Models\District::create(['name' => 'Asokore Mampong Municipal', 'region_id' => $region->id]);
    $community = Community::factory()->create([
        'name' => 'Asabi',
        'district_id' => $district->id,
        'latitude' => null,
        'longitude' => null,
    ]);

    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 6.7, 'longitude' => -1.5]]]),
        'api.open-meteo.com/*' => Http::response(['daily' => normalDaily()]),
    ]);

    $this->service->forCommunity($community);

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), 'geocoding-api.open-meteo.com')) {
            return false;
        }

        return $request['name'] === 'Asabi, Ashanti'
            && $request['countryCode'] === 'GH';
    });
});

test('does not geocode again once coordinates are already saved', function () {
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    fakeForecast(normalDaily());

    $this->service->forCommunity($community);

    Http::assertNotSent(fn($request) => str_contains($request->url(), 'geocoding-api.open-meteo.com'));
});

test('is marked unavailable when geocoding finds nothing', function () {
    $community = Community::factory()->create(['latitude' => null, 'longitude' => null]);

    fakeGeocode(null);

    $snapshot = $this->service->forCommunity($community);

    expect($snapshot->available)->toBeFalse();
});

test('is marked unavailable when the forecast request fails', function () {
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    Http::fake([
        'api.open-meteo.com/*' => Http::response([], 500),
    ]);

    $snapshot = $this->service->forCommunity($community);

    expect($snapshot->available)->toBeFalse();
});

test('classifies heavy rain when any of the next 3 days reaches the threshold', function () {
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    fakeForecast([
        'precipitation_sum' => [2, 25, 3],
        'temperature_2m_max' => [28, 29, 27],
        'windspeed_10m_max' => [15, 18, 12],
    ]);

    expect($this->service->forCommunity($community)->condition)->toBe('heavy_rain');
});

test('classifies strong wind when rain is fine but wind crosses the threshold', function () {
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    fakeForecast([
        'precipitation_sum' => [2, 5, 3],
        'temperature_2m_max' => [28, 29, 27],
        'windspeed_10m_max' => [15, 45, 12],
    ]);

    expect($this->service->forCommunity($community)->condition)->toBe('strong_wind');
});

test('classifies very hot when only temperature crosses the threshold', function () {
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    fakeForecast([
        'precipitation_sum' => [2, 5, 3],
        'temperature_2m_max' => [28, 36, 27],
        'windspeed_10m_max' => [15, 18, 12],
    ]);

    expect($this->service->forCommunity($community)->condition)->toBe('very_hot');
});

test('classifies normal when nothing crosses a threshold', function () {
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    fakeForecast(normalDaily());

    expect($this->service->forCommunity($community)->condition)->toBe('normal');
});

test('heavy rain takes priority over wind and heat when several thresholds are crossed', function () {
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    fakeForecast([
        'precipitation_sum' => [2, 25, 3],
        'temperature_2m_max' => [28, 36, 27],
        'windspeed_10m_max' => [15, 45, 12],
    ]);

    expect($this->service->forCommunity($community)->condition)->toBe('heavy_rain');
});

test('caches the forecast so a second call within the window does not hit the api again', function () {
    Cache::flush();
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    fakeForecast(normalDaily());

    $this->service->forCommunity($community);
    $this->service->forCommunity($community);

    Http::assertSentCount(1);
});
test('returns a day-by-day forecast with dates and conditions', function () {
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    Http::fake([
        'api.open-meteo.com/*' => Http::response(['daily' => [
            'time' => ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
            'precipitation_sum' => [2, 25, 3, 1, 0, 5, 2],
            'temperature_2m_max' => [28, 29, 27, 36, 30, 31, 29],
            'windspeed_10m_max' => [15, 18, 12, 20, 45, 22, 14],
        ]]),
    ]);

    $forecast = $this->service->extendedForecastFor($community);

    expect($forecast)->toHaveCount(7);
    expect($forecast[0]['date'])->toBe('2026-09-08');
    expect($forecast[1]['condition'])->toBe('heavy_rain');
    expect($forecast[2]['condition'])->toBe('normal');
    expect($forecast[3]['condition'])->toBe('very_hot');
    expect($forecast[4]['condition'])->toBe('strong_wind');
});

test('extended forecast is unavailable when coordinates cannot be resolved', function () {
    $community = Community::factory()->create(['latitude' => null, 'longitude' => null]);

    fakeGeocode(null);

    expect($this->service->extendedForecastFor($community))->toBeNull();
});

test('extended forecast is unavailable when the request fails', function () {
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    Http::fake([
        'api.open-meteo.com/*' => Http::response([], 500),
    ]);

    expect($this->service->extendedForecastFor($community))->toBeNull();
});

test('extended forecast defaults to 7 days but accepts a custom count', function () {
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    Http::fake([
        'api.open-meteo.com/*' => Http::response(['daily' => [
            'time' => ['2026-09-08', '2026-09-09', '2026-09-10'],
            'precipitation_sum' => [2, 2, 2],
            'temperature_2m_max' => [28, 28, 28],
            'windspeed_10m_max' => [15, 15, 15],
        ]]),
    ]);

    $forecast = $this->service->extendedForecastFor($community, days: 3);

    expect($forecast)->toHaveCount(3);
    Http::assertSent(fn($request) => str_contains($request->url(), 'forecast_days=3'));
});

test('extended forecast uses its own cache key separate from the 3-day summary', function () {
    Cache::flush();
    $community = Community::factory()->create(['latitude' => 6.7, 'longitude' => -1.5]);

    Http::fake([
        'api.open-meteo.com/*' => Http::response(['daily' => [
            'time' => ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
            'precipitation_sum' => [2, 2, 2, 2, 2, 2, 2],
            'temperature_2m_max' => [28, 28, 28, 28, 28, 28, 28],
            'windspeed_10m_max' => [15, 15, 15, 15, 15, 15, 15],
        ]]),
    ]);

    $this->service->forCommunity($community);
    $this->service->extendedForecastFor($community);

    Http::assertSentCount(2);
});
