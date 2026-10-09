<?php

use App\Providers\AppServiceProvider;

// boot() already ran once during app bootstrap for this test; re-registering with
// force triggers boot() again (Application::register() calls bootProvider() when
// the app is already booted) - the same pattern LogSmsProviderTest uses for register()
function rebootAppServiceProvider(): void
{
    app()->register(AppServiceProvider::class, force: true);
}

test('OTP_TEST_PHONES set in production refuses to boot', function () {
    app()->detectEnvironment(fn() => 'production');
    config(['otp.test_phones' => '0244009999']);

    expect(fn() => rebootAppServiceProvider())
        ->toThrow(RuntimeException::class, 'OTP_TEST_PHONES must not be set in production.');
});

test('an empty OTP_TEST_PHONES in production boots fine', function () {
    app()->detectEnvironment(fn() => 'production');
    config(['otp.test_phones' => '']);

    expect(fn() => rebootAppServiceProvider())->not->toThrow(RuntimeException::class);
});

test('local and testing environments never throw even with otp test phones set', function (string $environment) {
    app()->detectEnvironment(fn() => $environment);
    config(['otp.test_phones' => '0244009999']);

    expect(fn() => rebootAppServiceProvider())->not->toThrow(RuntimeException::class);
})->with(['local', 'testing']);
