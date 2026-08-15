<?php

use App\Services\OtpService;

test('it knows the four valid types', function () {
    expect(OtpService::TYPES)->toBe([
        'registration',
        'login',
        'password_reset',
        'phone_verification',
    ]);
});

test('it refuses an unknown type', function () {
    $service = app(OtpService::class);

    expect(fn() => $service->generate('+233241234567', 'nonsense'))
        ->toThrow(InvalidArgumentException::class);
});

test('it accepts each valid type', function (string $type) {
    $service = app(OtpService::class);

    expect($service->generate('+233241234567', $type)->type)->toBe($type);
})->with([
    'registration',
    'login',
    'password_reset',
    'phone_verification',
]);
