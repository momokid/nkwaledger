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

test('gives a maize-specific alert for heavy rain', function () {
    expect($this->advisor->alertFor('heavy_rain', 'Maize'))->toContain('blight');
});

test('gives a tomato-specific alert for heavy rain', function () {
    expect($this->advisor->alertFor('heavy_rain', 'Tomato'))->toContain('blight');
});

test('gives a tilapia-specific alert for very hot weather', function () {
    expect($this->advisor->alertFor('very_hot', 'Tilapia'))->toContain('oxygen');
});

test('gives a layers-specific alert for very hot weather', function () {
    expect($this->advisor->alertFor('very_hot', 'Layers'))->toContain('egg');
});

test('gives a plantain-specific alert for strong wind', function () {
    expect($this->advisor->alertFor('strong_wind', 'Plantain'))->toContain('wind');
});

test('a farm type with no specific alert returns null', function () {
    expect($this->advisor->alertFor('heavy_rain', 'Yam'))->toBeNull();
});

test('normal weather never produces a type-specific alert', function () {
    expect($this->advisor->alertFor('normal', 'Maize'))->toBeNull();
});

test('describes clear sky using the actual weather code', function () {
    expect($this->advisor->describeDay(0, 29))->toBe('Clear sky, around 29°C.');
});

test('describes partly cloudy using the actual weather code', function () {
    expect($this->advisor->describeDay(2, 27))->toBe('Partly cloudy, around 27°C.');
});

test('describes light rain using the actual weather code', function () {
    expect($this->advisor->describeDay(61, 25))->toBe('Slight rain, around 25°C.');
});

test('describes a thunderstorm using the actual weather code', function () {
    expect($this->advisor->describeDay(95, 30))->toBe('Thunderstorm, around 30°C.');
});

test('an unrecognised weather code still returns something sensible', function () {
    expect($this->advisor->describeDay(999, 28))->toBe('Weather conditions vary, around 28°C.');
});
test('outlook reports no risks when every day is normal', function () {
    $days = array_fill(0, 7, ['condition' => 'normal']);

    expect($this->advisor->outlookFor($days))->toBe('No significant weather risks expected over the next 7 days.');
});

test('outlook reports a single risk type', function () {
    $days = [
        ['condition' => 'heavy_rain'],
        ['condition' => 'heavy_rain'],
        ['condition' => 'heavy_rain'],
        ['condition' => 'normal'],
        ['condition' => 'normal'],
        ['condition' => 'normal'],
        ['condition' => 'normal'],
    ];

    expect($this->advisor->outlookFor($days))->toBe('Rain expected on 3 of the next 7 days.');
});

test('outlook combines two risk types', function () {
    $days = [
        ['condition' => 'heavy_rain'],
        ['condition' => 'heavy_rain'],
        ['condition' => 'very_hot'],
        ['condition' => 'very_hot'],
        ['condition' => 'normal'],
        ['condition' => 'normal'],
        ['condition' => 'normal'],
    ];

    expect($this->advisor->outlookFor($days))
        ->toBe('Rain expected on 2 of the next 7 days, and very hot conditions expected on 2 of the next 7 days.');
});

test('outlook combines all three risk types', function () {
    $days = [
        ['condition' => 'heavy_rain'],
        ['condition' => 'strong_wind'],
        ['condition' => 'very_hot'],
        ['condition' => 'normal'],
        ['condition' => 'normal'],
        ['condition' => 'normal'],
        ['condition' => 'normal'],
    ];

    expect($this->advisor->outlookFor($days))
        ->toBe('Rain expected on 1 of the next 7 days, strong winds expected on 1 of the next 7 days, and very hot conditions expected on 1 of the next 7 days.');
});

test('outlook works for a forecast shorter than 7 days', function () {
    $days = [
        ['condition' => 'heavy_rain'],
        ['condition' => 'normal'],
        ['condition' => 'normal'],
    ];

    expect($this->advisor->outlookFor($days))->toBe('Rain expected on 1 of the next 3 days.');
});
