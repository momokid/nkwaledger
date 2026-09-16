<?php

use App\Contracts\SmsProvider;
use App\Mail\OtpMail;
use App\Services\OtpService;
use Illuminate\Support\Facades\Mail;

test('the sms channel is the default and behaves as before', function () {
    app(OtpService::class)->generate('0241234567', 'login');

    expect(app(SmsProvider::class)->sentTo('0241234567'))->toBeTrue();
});

test('an explicit sms channel sends an sms, not an email', function () {
    Mail::fake();

    app(OtpService::class)->generate('0241234568', 'invitation', 'sms');

    expect(app(SmsProvider::class)->sentTo('0241234568'))->toBeTrue();
    Mail::assertNothingSent();
});

test('an email channel sends a mailable instead of an sms', function () {
    Mail::fake();

    app(OtpService::class)->generate('kofi@nkwaledger.com', 'invitation', 'email');

    Mail::assertSent(OtpMail::class);
    expect(app(SmsProvider::class)->sentTo('kofi@nkwaledger.com'))->toBeFalse();
});

test('the emailed code carries the same wording as the sms message', function () {
    Mail::fake();

    app(OtpService::class)->generate('kofi@nkwaledger.com', 'invitation', 'email');

    Mail::assertSent(OtpMail::class, function (OtpMail $mail) {
        return str_contains($mail->bodyText, '/activate') && str_contains($mail->bodyText, '1 hour');
    });
});

test('the otp record is stored against the email identifier', function () {
    Mail::fake();

    $otp = app(OtpService::class)->generate('kofi@nkwaledger.com', 'invitation', 'email');

    expect($otp->identifier)->toBe('kofi@nkwaledger.com');
});

// the bypass allow-list only ever holds phone numbers, so an email identifier can never match it
test('a bypass-listed phone does not affect an unrelated email channel send', function () {
    config(['otp.test_phones' => '0244009999']);
    Mail::fake();

    app(OtpService::class)->generate('kofi@nkwaledger.com', 'invitation', 'email');

    Mail::assertSent(OtpMail::class);
});
