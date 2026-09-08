<?php

use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\WeatherAdvisoryLog;
use Illuminate\Support\Facades\Http;

function fakeCommandWeather(array $daily): void
{
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 6.7, 'longitude' => -1.5]]]),
        'api.open-meteo.com/*' => Http::response(['daily' => $daily]),
    ]);
}

beforeEach(function () {
    $this->cropCategory = FarmTypeCategory::create(['name' => 'Crop']);
    $this->type = FarmType::create(['name' => 'Maize', 'category_id' => $this->cropCategory->id]);
    $this->profile = FarmerProfile::factory()->create();
});

test('logs a snapshot for a community with a farm unit', function () {
    fakeCommandWeather([
        'precipitation_sum' => [25, 5, 3],
        'temperature_2m_max' => [28, 29, 27],
        'windspeed_10m_max' => [15, 18, 12],
        'weathercode' => [61, 61, 61],
    ]);

    $community = Community::factory()->create();
    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $this->type->id,
        'community_id' => $community->id,
    ]);

    $this->artisan('weather:snapshot-advisories')->assertExitCode(0);

    $log = WeatherAdvisoryLog::where('community_id', $community->id)->first();
    expect($log)->not->toBeNull();
    expect($log->condition)->toBe('heavy_rain');
    expect($log->headline)->toBe('Heavy rain expected');
    expect($log->date->toDateString())->toBe(now()->toDateString());
});

test('stores the actual weather description for a normal day, not a generic message', function () {
    fakeCommandWeather([
        'precipitation_sum' => [2, 5, 3],
        'temperature_2m_max' => [28, 29, 27],
        'windspeed_10m_max' => [15, 18, 12],
        'weathercode' => [61, 61, 61],
    ]);

    $community = Community::factory()->create();
    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $this->type->id,
        'community_id' => $community->id,
    ]);

    $this->artisan('weather:snapshot-advisories');

    $log = WeatherAdvisoryLog::where('community_id', $community->id)->first();
    expect($log->condition)->toBe('normal');
    expect($log->headline)->toBe('Slight rain, around 28°C.');
});

test('does not create a duplicate log for the same community on the same day', function () {
    fakeCommandWeather([
        'precipitation_sum' => [2, 5, 3],
        'temperature_2m_max' => [28, 29, 27],
        'windspeed_10m_max' => [15, 18, 12],
        'weathercode' => [0, 0, 0],
    ]);

    $community = Community::factory()->create();
    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $this->type->id,
        'community_id' => $community->id,
    ]);

    $this->artisan('weather:snapshot-advisories');
    $this->artisan('weather:snapshot-advisories');

    expect(WeatherAdvisoryLog::where('community_id', $community->id)->count())->toBe(1);
});

test('skips a community with no farm units', function () {
    fakeCommandWeather([
        'precipitation_sum' => [2, 5, 3],
        'temperature_2m_max' => [28, 29, 27],
        'windspeed_10m_max' => [15, 18, 12],
        'weathercode' => [0, 0, 0],
    ]);

    Community::factory()->create();

    $this->artisan('weather:snapshot-advisories');

    expect(WeatherAdvisoryLog::count())->toBe(0);
});

test('skips a community when weather is unavailable', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => []]),
        'api.open-meteo.com/*' => Http::response([], 500),
    ]);

    $community = Community::factory()->create(['latitude' => null, 'longitude' => null]);
    FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->profile->id,
        'farm_type_id' => $this->type->id,
        'community_id' => $community->id,
    ]);

    $this->artisan('weather:snapshot-advisories');

    expect(WeatherAdvisoryLog::count())->toBe(0);
});
