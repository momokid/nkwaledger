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
