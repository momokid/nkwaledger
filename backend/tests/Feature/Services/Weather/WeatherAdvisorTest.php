<?php

use App\Services\Weather\WeatherAdvisor;

beforeEach(function () {
    $this->advisor = new WeatherAdvisor();
});

test('gives a headline for each condition', function () {
    expect($this->advisor->headline('heavy_rain'))->toBe('Heavy rain expected');
    expect($this->advisor->headline('strong_wind'))->toBe('Strong winds expected');
    expect($this->advisor->headline('very_hot'))->toBe('Very hot weather expected');
    expect($this->advisor->headline('normal'))->toBe('No significant weather risks');
});

test('gives crop-specific advice for heavy rain', function () {
    expect($this->advisor->adviceFor('heavy_rain', 'Crop'))
        ->toContain('harvesting early');
});

test('gives livestock-specific advice for heavy rain', function () {
    expect($this->advisor->adviceFor('heavy_rain', 'Livestock'))
        ->toContain('shelters are dry');
});

test('gives aquatic-specific advice for heavy rain', function () {
    expect($this->advisor->adviceFor('heavy_rain', 'Aquatic'))
        ->toContain('pond banks');
});

test('gives different advice per category for very hot weather', function () {
    $crop = $this->advisor->adviceFor('very_hot', 'Crop');
    $livestock = $this->advisor->adviceFor('very_hot', 'Livestock');
    $aquatic = $this->advisor->adviceFor('very_hot', 'Aquatic');

    expect($crop)->not->toBe($livestock);
    expect($livestock)->not->toBe($aquatic);
});

test('gives different advice per category for strong wind', function () {
    $crop = $this->advisor->adviceFor('strong_wind', 'Crop');
    $livestock = $this->advisor->adviceFor('strong_wind', 'Livestock');

    expect($crop)->not->toBe($livestock);
});

test('normal weather gives the same reassuring message for every category', function () {
    expect($this->advisor->adviceFor('normal', 'Crop'))
        ->toBe($this->advisor->adviceFor('normal', 'Livestock'));
});

test('an unknown category falls back to a generic message, never breaks', function () {
    expect($this->advisor->adviceFor('heavy_rain', 'Something New'))
        ->toBeString();
});
