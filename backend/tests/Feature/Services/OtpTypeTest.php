<?php

use App\Services\OtpService;

test('it knows the five valid types', function () {
    expect(OtpService::TYPES)->toBe([
        'registration',
        'login',
        'password_reset',
        'phone_verification',
        'invitation',
    ]);
});

test('it refuses an unknown type', function () {
    $service = app(OtpService::class);

    expect(fn() => $service->generate('0241234567', 'nonsense'))
        ->toThrow(InvalidArgumentException::class);
});

test('it accepts each valid type', function (string $type) {
    $service = app(OtpService::class);

    expect($service->generate('0241234567', $type)->type)->toBe($type);
})->with([
    'registration',
    'login',
    'password_reset',
    'phone_verification',
    'invitation',
]);

test('most codes last five minutes', function (string $type) {
    $otp = app(OtpService::class)->generate('0241234567', $type);

    expect(now()->diffInMinutes($otp->expires_at))->toBeGreaterThan(4);
    expect(now()->diffInMinutes($otp->expires_at))->toBeLessThan(6);
})->with([
    'registration',
    'login',
    'password_reset',
    'phone_verification',
]);

// an invited person may not sit down at a computer until hours later
test('an invitation code lasts an hour', function () {
    $otp = app(OtpService::class)->generate('0241234567', 'invitation');

    expect(now()->diffInMinutes($otp->expires_at))->toBeGreaterThan(58);
    expect(now()->diffInMinutes($otp->expires_at))->toBeLessThan(62);
});

test('an invitation sms explains what to do', function () {
    app(OtpService::class)->generate('0241234567', 'invitation');

    $message = collect(app(App\Contracts\SmsProvider::class)->sent)->last()['message'];

    expect($message)->toContain('Activate');
});
