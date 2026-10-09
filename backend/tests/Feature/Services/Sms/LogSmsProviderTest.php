<?php

use App\Contracts\SmsProvider;
use App\Providers\AppServiceProvider;
use App\Services\Sms\ArkeselSmsProvider;
use App\Services\Sms\LogSmsProvider;
use Illuminate\Support\Facades\Log;

// the container already holds a FakeSmsProvider instance (bound in TestCase for
// every other test); drop it here so resolving SmsProvider falls through to
// whatever AppServiceProvider::register() actually binds
function reregisterSmsProvider(): void
{
    app()->forgetInstance(SmsProvider::class);
    app()->register(AppServiceProvider::class, force: true);
}

// putenv/$_ENV/$_SERVER are process-level, not per-test, so a test that changes
// SMS_DRIVER must put back exactly what was there before it ran - not just unset it
beforeEach(function () {
    $this->originalPutenv = getenv('SMS_DRIVER');
    $this->originalEnvSet = array_key_exists('SMS_DRIVER', $_ENV);
    $this->originalEnvValue = $_ENV['SMS_DRIVER'] ?? null;
    $this->originalServerSet = array_key_exists('SMS_DRIVER', $_SERVER);
    $this->originalServerValue = $_SERVER['SMS_DRIVER'] ?? null;

    // CI has no arkesel secrets configured, so resolving a real ArkeselSmsProvider
    // there hits its strict string constructor with nulls - snapshot so tests that
    // need a real instance can set safe values without leaking them to other tests
    $this->originalArkeselKey = config('services.arkesel.key');
    $this->originalArkeselSender = config('services.arkesel.sender');
});

afterEach(function () {
    if ($this->originalPutenv === false) {
        putenv('SMS_DRIVER');
    } else {
        putenv("SMS_DRIVER={$this->originalPutenv}");
    }

    if ($this->originalEnvSet) {
        $_ENV['SMS_DRIVER'] = $this->originalEnvValue;
    } else {
        unset($_ENV['SMS_DRIVER']);
    }

    if ($this->originalServerSet) {
        $_SERVER['SMS_DRIVER'] = $this->originalServerValue;
    } else {
        unset($_SERVER['SMS_DRIVER']);
    }

    config([
        'services.arkesel.key' => $this->originalArkeselKey,
        'services.arkesel.sender' => $this->originalArkeselSender,
    ]);
});

test('SMS_DRIVER=log binds the log sms provider', function () {
    putenv('SMS_DRIVER=log');
    $_ENV['SMS_DRIVER'] = 'log';

    reregisterSmsProvider();

    expect(app(SmsProvider::class))->toBeInstanceOf(LogSmsProvider::class);
});

test('an unset SMS_DRIVER still binds arkesel, the default', function () {
    putenv('SMS_DRIVER');
    unset($_ENV['SMS_DRIVER'], $_SERVER['SMS_DRIVER']);

    // arkesel's constructor takes plain strings; give it valid ones regardless of
    // whether the running environment has real arkesel secrets configured
    config([
        'services.arkesel.key' => 'test-key',
        'services.arkesel.sender' => 'test-sender',
    ]);

    reregisterSmsProvider();

    expect(app(SmsProvider::class))->toBeInstanceOf(ArkeselSmsProvider::class);
});

test('the log sms provider writes to the sms channel, not the default log', function () {
    Log::shouldReceive('channel')->once()->with('sms')->andReturnSelf();
    Log::shouldReceive('info')->once()->with('SMS to 0244001001: Your code is 112233');

    (new LogSmsProvider())->send('0244001001', 'Your code is 112233');
});
