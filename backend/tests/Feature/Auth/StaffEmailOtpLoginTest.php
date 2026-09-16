<?php

use App\Contracts\SmsProvider;
use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function staffLoginAs(string $identifier, string $password = 'Password@123')
{
    return test()->post('/login', [
        'identifier' => $identifier,
        'password'   => $password,
    ]);
}

test('a tracked-role user logging in by email is sent the login otp by mail, not sms', function () {
    Mail::fake();

    $agent = User::factory()->create([
        'phone'    => '0244000201',
        'email'    => 'agent201@nkwaledger.com',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    $response = staffLoginAs('agent201@nkwaledger.com');

    $response->assertRedirect('/verify-otp');
    Mail::assertSent(OtpMail::class);
    expect(app(SmsProvider::class)->sentTo('0244000201'))->toBeFalse();
});

test('the login otp is stored against the email when logging in by email', function () {
    Mail::fake();

    $agent = User::factory()->create([
        'phone'    => '0244000202',
        'email'    => 'agent202@nkwaledger.com',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    staffLoginAs('agent202@nkwaledger.com');

    expect(OtpCode::where('identifier', 'agent202@nkwaledger.com')->where('type', 'login')->exists())->toBeTrue();
});

test('a tracked-role user logging in by phone still gets the code by sms', function () {
    Mail::fake();

    $agent = User::factory()->create([
        'phone'    => '0244000203',
        'email'    => 'agent203@nkwaledger.com',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    staffLoginAs('0244000203');

    expect(app(SmsProvider::class)->sentTo('0244000203'))->toBeTrue();
    Mail::assertNothingSent();
});

test('a farmer with an email on file is never sent a login otp by email or sms', function () {
    Mail::fake();

    $farmer = User::factory()->create([
        'phone'    => '0244000204',
        'email'    => 'farmer204@nkwaledger.com',
        'password' => bcrypt('Password@123'),
    ]);
    $farmer->assignRole('farmer');

    $response = staffLoginAs('farmer204@nkwaledger.com');

    $response->assertRedirect('/farmer/dashboard');
    Mail::assertNothingSent();
    expect(app(SmsProvider::class)->sentTo('0244000204'))->toBeFalse();
});

test('the sms fallback sends a fresh code to the phone on file, not the email', function () {
    Mail::fake();

    $agent = User::factory()->create([
        'phone'    => '0244000205',
        'email'    => 'agent205@nkwaledger.com',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    staffLoginAs('agent205@nkwaledger.com');

    $this->post('/resend-otp', ['sms_fallback' => true]);

    expect(app(SmsProvider::class)->sentTo('0244000205'))->toBeTrue();
});

test('after the sms fallback, the phone code verifies and logs them in', function () {
    Mail::fake();

    $agent = User::factory()->create([
        'phone'    => '0244000206',
        'email'    => 'agent206@nkwaledger.com',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    staffLoginAs('agent206@nkwaledger.com');
    $this->post('/resend-otp', ['sms_fallback' => true]);

    OtpCode::where('identifier', '0244000206')->update(['code' => Hash::make('112233')]);

    $response = $this->post('/verify-otp', ['code' => '112233']);

    $response->assertRedirect('/agent/dashboard');
    $this->assertAuthenticatedAs($agent);
});

// same source of truth as the login-window throttle: a bypass via channel-hopping would
// let someone rack up far more sms sends than the configured hourly cap allows
test('the resend budget is shared across the email attempt and its sms fallback, so channel-hopping cannot bypass it', function () {
    Mail::fake();

    $agent = User::factory()->create([
        'phone'    => '0244000207',
        'email'    => 'agent207@nkwaledger.com',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    staffLoginAs('agent207@nkwaledger.com');

    for ($i = 0; $i < 3; $i++) {
        $this->post('/resend-otp')->assertStatus(302);
    }

    $this->post('/resend-otp', ['sms_fallback' => true])->assertStatus(429);
});

// the fallback must run through the exact same OtpService::generate() call as every other
// flow, so a developer's own listed number never fires a real sms just by using this button
test('the sms fallback honors the otp test-phone bypass and sends no real sms', function () {
    config(['otp.test_phones' => '0244000209']);
    Mail::fake();

    $agent = User::factory()->create([
        'phone'    => '0244000209',
        'email'    => 'agent209@nkwaledger.com',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    staffLoginAs('agent209@nkwaledger.com');

    $this->post('/resend-otp', ['sms_fallback' => true]);

    expect(app(SmsProvider::class)->sentTo('0244000209'))->toBeFalse();

    $otp = OtpCode::where('identifier', '0244000209')->where('type', 'login')->latest()->first();
    expect($otp)->not->toBeNull();
    expect(Hash::check('000000', $otp->code))->toBeTrue();
});

test('a login otp verified after arriving by email does not mark the phone verified', function () {
    Mail::fake();

    $agent = User::factory()->unverified()->create([
        'phone'    => '0244000208',
        'email'    => 'agent208@nkwaledger.com',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    staffLoginAs('agent208@nkwaledger.com');

    OtpCode::where('identifier', 'agent208@nkwaledger.com')->update(['code' => Hash::make('112233')]);

    $this->post('/verify-otp', ['code' => '112233']);

    expect($agent->fresh()->phone_verified_at)->toBeNull();
});
