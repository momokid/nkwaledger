<?php

use App\Contracts\SmsProvider;
use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('a tracked-role user\'s periodic re-verification code is sent by email by default', function () {
    Mail::fake();

    $agent = User::factory()->create(['phone' => '0244000301', 'email' => 'agent301@nkwaledger.com']);
    $agent->assignRole('agent');

    $this->actingAs($agent)->post('/verify-phone/send');

    Mail::assertSent(OtpMail::class);
    expect(OtpCode::where('identifier', 'agent301@nkwaledger.com')->where('type', 'phone_verification')->exists())->toBeTrue();
    expect(app(SmsProvider::class)->sentTo('0244000301'))->toBeFalse();
});

test('a tracked-role user with no email on file falls back to sms', function () {
    Mail::fake();

    $vet = User::factory()->create(['phone' => '0244000302', 'email' => null]);
    $vet->assignRole('vet');

    $this->actingAs($vet)->post('/verify-phone/send');

    expect(app(SmsProvider::class)->sentTo('0244000302'))->toBeTrue();
    Mail::assertNothingSent();
});

test('a farmer\'s periodic re-verification is always sent by sms, unaffected', function () {
    Mail::fake();

    $farmer = User::factory()->create(['phone' => '0244000303', 'email' => 'farmer303@nkwaledger.com']);
    $farmer->assignRole('farmer');

    $this->actingAs($farmer)->post('/verify-phone/send');

    expect(app(SmsProvider::class)->sentTo('0244000303'))->toBeTrue();
    Mail::assertNothingSent();
});

test('the sms fallback sends the code to the phone instead of email for a tracked role', function () {
    Mail::fake();

    $agent = User::factory()->create(['phone' => '0244000304', 'email' => 'agent304@nkwaledger.com']);
    $agent->assignRole('agent');

    $this->actingAs($agent)->post('/verify-phone/send', ['sms_fallback' => true]);

    expect(app(SmsProvider::class)->sentTo('0244000304'))->toBeTrue();
    Mail::assertNothingSent();
});

test('confirming an emailed code verifies the phone', function () {
    Mail::fake();

    $agent = User::factory()->unverified()->create(['phone' => '0244000305', 'email' => 'agent305@nkwaledger.com']);
    $agent->assignRole('agent');

    $this->actingAs($agent)->post('/verify-phone/send');

    OtpCode::where('identifier', 'agent305@nkwaledger.com')->update(['code' => Hash::make('112233')]);

    $this->actingAs($agent)->post('/verify-phone/confirm', ['code' => '112233']);

    expect($agent->fresh()->phone_verified_at)->not->toBeNull();
});

test('confirming a code sent via the sms fallback verifies the phone', function () {
    Mail::fake();

    $agent = User::factory()->unverified()->create(['phone' => '0244000306', 'email' => 'agent306@nkwaledger.com']);
    $agent->assignRole('agent');

    $this->actingAs($agent)->post('/verify-phone/send', ['sms_fallback' => true]);

    OtpCode::where('identifier', '0244000306')->update(['code' => Hash::make('112233')]);

    $this->actingAs($agent)->post('/verify-phone/confirm', ['code' => '112233']);

    expect($agent->fresh()->phone_verified_at)->not->toBeNull();
});

test('the stale email code no longer verifies after switching to the sms fallback', function () {
    Mail::fake();

    $agent = User::factory()->unverified()->create(['phone' => '0244000307', 'email' => 'agent307@nkwaledger.com']);
    $agent->assignRole('agent');

    $this->actingAs($agent)->post('/verify-phone/send');
    OtpCode::where('identifier', 'agent307@nkwaledger.com')->update(['code' => Hash::make('654321')]);

    $this->actingAs($agent)->post('/verify-phone/send', ['sms_fallback' => true]);

    $this->actingAs($agent)->post('/verify-phone/confirm', ['code' => '654321'])
        ->assertSessionHasErrors('code');

    expect($agent->fresh()->phone_verified_at)->toBeNull();
});
