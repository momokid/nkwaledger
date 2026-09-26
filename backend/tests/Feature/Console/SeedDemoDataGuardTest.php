<?php

use App\Console\Commands\SeedDemoData;

test('demo:seed refuses to run in production', function () {
    app()->detectEnvironment(fn() => 'production');

    $command = app(SeedDemoData::class);

    expect(fn() => $command->guardAgainstProduction())
        ->toThrow(RuntimeException::class, 'Demo data seeding is not permitted in production.');
});

test('demo:seed does not throw in local or testing environments', function (string $environment) {
    app()->detectEnvironment(fn() => $environment);

    $command = app(SeedDemoData::class);

    expect(fn() => $command->guardAgainstProduction())->not->toThrow(RuntimeException::class);
})->with(['local', 'testing']);
