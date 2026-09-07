<?php

use App\Models\Community;
use Illuminate\Support\Facades\Http;

test('geocodes every community missing coordinates', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 6.7, 'longitude' => -1.5]]]),
    ]);

    $missing = Community::factory()->count(2)->create(['latitude' => null, 'longitude' => null]);
    $already = Community::factory()->create(['latitude' => 5.5, 'longitude' => -0.2]);

    $this->artisan('weather:geocode-communities')->assertSuccessful();

    expect($missing[0]->fresh()->latitude)->not->toBeNull();
    expect($missing[1]->fresh()->latitude)->not->toBeNull();
});

test('does not call geocoding for a community that already has coordinates', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 6.7, 'longitude' => -1.5]]]),
    ]);

    Community::factory()->create(['latitude' => 5.5, 'longitude' => -0.2]);

    $this->artisan('weather:geocode-communities')->assertSuccessful();

    Http::assertNothingSent();
});

test('reports success even when nothing needs geocoding', function () {
    Community::factory()->create(['latitude' => 5.5, 'longitude' => -0.2]);

    $this->artisan('weather:geocode-communities')
        ->expectsOutputToContain('Every community already has coordinates.')
        ->assertSuccessful();
});

test('warns about a community that could not be resolved, but still succeeds', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => []]),
    ]);

    Community::factory()->create(['name' => 'Nowhereville', 'latitude' => null, 'longitude' => null]);

    $this->artisan('weather:geocode-communities')
        ->expectsOutputToContain('Nowhereville')
        ->assertSuccessful();
});
