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

    expect($message)->toContain('/activate');
    expect($message)->toContain('1 hour');
});

// a developer testing locally needs a known code without a working sms provider — but
// only for an explicitly listed number, and never anywhere but local/testing
test('a listed test phone gets the fixed bypass code instead of a random one', function () {
    config(['otp.test_phones' => '0244009999']);

    $otp = app(OtpService::class)->generate('0244009999', 'login');

    expect(Illuminate\Support\Facades\Hash::check('000000', $otp->code))->toBeTrue();
});

test('a listed test phone sends no real sms', function () {
    config(['otp.test_phones' => '0244009999']);

    app(OtpService::class)->generate('0244009999', 'login');

    expect(app(App\Contracts\SmsProvider::class)->sentTo('0244009999'))->toBeFalse();
});

test('a phone not on the list still gets a real random code and a real sms', function () {
    config(['otp.test_phones' => '0244009999']);

    $otp = app(OtpService::class)->generate('0244000001', 'login');

    expect(Illuminate\Support\Facades\Hash::check('000000', $otp->code))->toBeFalse();
    expect(app(App\Contracts\SmsProvider::class)->sentTo('0244000001'))->toBeTrue();
});

test('the bypass never activates outside local or testing environments', function () {
    config(['otp.test_phones' => '0244009999']);
    app()->detectEnvironment(fn() => 'production');

    $otp = app(OtpService::class)->generate('0244009999', 'login');

    app()->detectEnvironment(fn() => 'testing');

    expect(Illuminate\Support\Facades\Hash::check('000000', $otp->code))->toBeFalse();
    expect(app(App\Contracts\SmsProvider::class)->sentTo('0244009999'))->toBeTrue();
});

test('several test phones can be listed at once, comma separated', function () {
    config(['otp.test_phones' => '0244009999, 0244008888']);

    $first = app(OtpService::class)->generate('0244009999', 'login');
    $second = app(OtpService::class)->generate('0244008888', 'login');

    expect(Illuminate\Support\Facades\Hash::check('000000', $first->code))->toBeTrue();
    expect(Illuminate\Support\Facades\Hash::check('000000', $second->code))->toBeTrue();
});

test('a fresh code counts as live', function () {
    app(OtpService::class)->generate('0244001101', 'invitation');

    expect(app(OtpService::class)->hasLiveCode('0244001101', 'invitation'))->toBeTrue();
});

test('no code at all is not live', function () {
    expect(app(OtpService::class)->hasLiveCode('0244001102', 'invitation'))->toBeFalse();
});

test('an expired code is not live', function () {
    App\Models\OtpCode::create([
        'identifier' => '0244001103',
        'code'       => Illuminate\Support\Facades\Hash::make('112233'),
        'type'       => 'invitation',
        'expires_at' => now()->subMinute(),
    ]);

    expect(app(OtpService::class)->hasLiveCode('0244001103', 'invitation'))->toBeFalse();
});

test('a used code is not live', function () {
    $otp = app(OtpService::class)->generate('0244001104', 'invitation');
    $otp->update(['used_at' => now()]);

    expect(app(OtpService::class)->hasLiveCode('0244001104', 'invitation'))->toBeFalse();
});

// three wrong guesses void a code, so it cannot be handed out again as if it were usable
test('an exhausted code is not live', function () {
    $otp = app(OtpService::class)->generate('0244001105', 'invitation');
    $otp->update(['attempts' => 3]);

    expect(app(OtpService::class)->hasLiveCode('0244001105', 'invitation'))->toBeFalse();
});

test('a code of another type does not count', function () {
    app(OtpService::class)->generate('0244001106', 'login');

    expect(app(OtpService::class)->hasLiveCode('0244001106', 'invitation'))->toBeFalse();
});
