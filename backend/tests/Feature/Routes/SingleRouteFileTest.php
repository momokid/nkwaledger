<?php

test('there is only one web route file', function () {
    expect(file_exists(base_path('routes/auth.php')))->toBeFalse();
});

test('web routes are the ones actually loaded', function () {
    expect(collect(app('router')->getRoutes())->isNotEmpty())->toBeTrue();
    expect(route('login', absolute: false))->toBe('/login');
});
