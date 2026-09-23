<?php

use App\Contracts\SmsProvider;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function invitedStaff(string $phone = '0244001001', string $role = 'agent'): User
{
    $user = User::factory()->unverified()->create([
        'phone'    => $phone,
        'password' => null,
    ]);

    $user->assignRole($role);

    return $user;
}

function liveInvitation(string $phone, string $code = '112233'): OtpCode
{
    return OtpCode::create([
        'identifier' => $phone,
        'code'       => Hash::make($code),
        'type'       => 'invitation',
        'expires_at' => now()->addHour(),
    ]);
}

function invitedStaffWithEmail(string $phone, string $email, string $role = 'supplier'): User
{
    $user = User::factory()->unverified()->create([
        'phone'    => $phone,
        'email'    => $email,
        'password' => null,
    ]);

    $user->assignRole($role);

    return $user;
}

function liveEmailInvitation(string $email, string $code = '223344'): OtpCode
{
    return OtpCode::create([
        'identifier' => $email,
        'code'       => Hash::make($code),
        'type'       => 'invitation',
        'expires_at' => now()->addHour(),
    ]);
}

function expiredEmailInvitation(string $email, string $code = '223344'): OtpCode
{
    return OtpCode::create([
        'identifier' => $email,
        'code'       => Hash::make($code),
        'type'       => 'invitation',
        'expires_at' => now()->subMinutes(5),
    ]);
}

test('the page is open to anyone', function () {
    $this->get('/activate')->assertOk();
});

test('a live invitation forwards them to the code screen', function () {
    $user = invitedStaff();
    liveInvitation('0244001001');

    $this->post('/activate', ['phone' => '0244001001'])
        ->assertRedirect('/verify-otp');
});

test('the session is set so the code screen knows who is verifying', function () {
    invitedStaff();
    liveInvitation('0244001001');

    $this->post('/activate', ['phone' => '0244001001']);

    expect(session('auth.login_identifier'))->toBe('0244001001');
    expect(session('auth.otp_type'))->toBe('invitation');
});

// the code went out with the invitation, so claiming it must not cost a second message
test('claiming a live invitation sends no new sms', function () {
    invitedStaff();
    liveInvitation('0244001001');

    $this->post('/activate', ['phone' => '0244001001']);

    expect(app(SmsProvider::class)->sentTo('0244001001'))->toBeFalse();
});

test('the original code still works after claiming', function () {
    invitedStaff();
    liveInvitation('0244001001');

    $this->post('/activate', ['phone' => '0244001001']);

    $this->post('/verify-otp', ['code' => '112233'])
        ->assertRedirect('/set-password');
});

// an expired invitation is a real case, since the code lasts an hour and people come back later
test('an expired invitation gets a fresh code', function () {
    invitedStaff();

    OtpCode::create([
        'identifier' => '0244001001',
        'code'       => Hash::make('112233'),
        'type'       => 'invitation',
        'expires_at' => now()->subMinutes(5),
    ]);

    $this->post('/activate', ['phone' => '0244001001']);

    expect(app(SmsProvider::class)->sentTo('0244001001'))->toBeTrue();
});

test('an already used invitation gets a fresh code', function () {
    invitedStaff();

    $otp = liveInvitation('0244001001');
    $otp->update(['used_at' => now()]);

    $this->post('/activate', ['phone' => '0244001001']);

    expect(app(SmsProvider::class)->sentTo('0244001001'))->toBeTrue();
});

test('a local and international spelling both reach the same account', function (string $spelling) {
    invitedStaff();
    liveInvitation('0244001001');

    $this->post('/activate', ['phone' => $spelling]);

    expect(session('auth.login_identifier'))->toBe('0244001001');
})->with(['0244001001', '+233244001001', '024 400 1001']);

test('an unknown number gets the same reply', function () {
    $this->post('/activate', ['phone' => '0249999999'])
        ->assertRedirect('/verify-otp')
        ->assertSessionHasNoErrors();
});

test('an unknown number never triggers an sms', function () {
    $this->post('/activate', ['phone' => '0249999999']);

    expect(app(SmsProvider::class)->sentTo('0249999999'))->toBeFalse();
});

// an active account has nothing to activate, and must not be told whether it exists
test('an already active account gets the same reply and no code', function () {
    $active = User::factory()->create(['phone' => '0244001002', 'password' => bcrypt('Password@123')]);
    $active->assignRole('agent');

    $this->post('/activate', ['phone' => '0244001002'])
        ->assertRedirect('/verify-otp');

    expect(app(SmsProvider::class)->sentTo('0244001002'))->toBeFalse();
    expect(OtpCode::where('identifier', '0244001002')->exists())->toBeFalse();
});

test('a malformed number is refused', function () {
    $this->post('/activate', ['phone' => 'not a phone'])
        ->assertSessionHasErrors('phone');
});

// regression: an sms-channel invite must keep activating exactly as it always has
test('an sms-channel invite activates as before, and the channel is sms', function () {
    invitedStaff('0244002001');
    liveInvitation('0244002001');

    $this->post('/activate', ['phone' => '0244002001'])
        ->assertRedirect('/verify-otp');

    expect(session('auth.login_identifier'))->toBe('0244002001')
        ->and(session('auth.otp_channel'))->toBe('sms');

    $this->post('/verify-otp', ['code' => '112233'])
        ->assertRedirect('/set-password');
});

test('an sms-channel invite shows the phone channel on the verify-otp page', function () {
    invitedStaff('0244002002');
    liveInvitation('0244002002');

    $this->post('/activate', ['phone' => '0244002002']);

    $this->get('/verify-otp')->assertInertia(
        fn($page) => $page->component('Auth/VerifyOtp')->where('channel', 'sms')
    );
});

// the bug being fixed: an email-channel invite's code lives under the email, not the phone
test('an email-channel invite activates using the emailed code, and the channel is email', function () {
    invitedStaffWithEmail('0244002003', 'kwame@example.com');
    liveEmailInvitation('kwame@example.com', '223344');

    $this->post('/activate', ['phone' => '0244002003'])
        ->assertRedirect('/verify-otp');

    expect(session('auth.login_identifier'))->toBe('kwame@example.com')
        ->and(session('auth.otp_channel'))->toBe('email');

    $this->post('/verify-otp', ['code' => '223344'])
        ->assertRedirect('/set-password');
});

test('an email-channel invite sends no new sms or email, the same way a live sms invite does not', function () {
    invitedStaffWithEmail('0244002004', 'ama@example.com');
    liveEmailInvitation('ama@example.com', '223344');

    $this->post('/activate', ['phone' => '0244002004']);

    expect(app(SmsProvider::class)->sentTo('0244002004'))->toBeFalse();
    expect(OtpCode::where('identifier', 'ama@example.com')->count())->toBe(1);
});

test('an email-channel invite shows the email channel on the verify-otp page', function () {
    invitedStaffWithEmail('0244002005', 'akosua@example.com');
    liveEmailInvitation('akosua@example.com', '223344');

    $this->post('/activate', ['phone' => '0244002005']);

    $this->get('/verify-otp')->assertInertia(
        fn($page) => $page->component('Auth/VerifyOtp')->where('channel', 'email')
    );
});

// an expired emailed code is a real case too, same as an expired sms one - falls back to a fresh sms
test('an email-channel invite whose code expired falls back to a fresh sms code, and the channel becomes sms', function () {
    invitedStaffWithEmail('0244002006', 'yaw@example.com');
    expiredEmailInvitation('yaw@example.com', '223344');

    $this->post('/activate', ['phone' => '0244002006'])
        ->assertRedirect('/verify-otp');

    expect(app(SmsProvider::class)->sentTo('0244002006'))->toBeTrue();
    expect(session('auth.login_identifier'))->toBe('0244002006')
        ->and(session('auth.otp_channel'))->toBe('sms');
});

test('an email-channel invite with no live code anywhere falls back to sms, same as no invite at all', function () {
    invitedStaffWithEmail('0244002007', 'kofi@example.com');
    // no OtpCode at all under either identifier

    $this->post('/activate', ['phone' => '0244002007'])
        ->assertRedirect('/verify-otp');

    expect(app(SmsProvider::class)->sentTo('0244002007'))->toBeTrue();
    expect(session('auth.otp_channel'))->toBe('sms');
});

// the "reply looks the same" property must hold for both channels, not just sms
test('an unknown number gets the same session shape and default channel as a real pending one with no live code', function () {
    $this->post('/activate', ['phone' => '0249998888'])
        ->assertRedirect('/verify-otp')
        ->assertSessionHasNoErrors();

    expect(session()->has('auth.login_identifier'))->toBeTrue()
        ->and(session()->has('auth.otp_type'))->toBeTrue()
        ->and(session()->has('auth.otp_channel'))->toBeTrue()
        ->and(session('auth.login_identifier'))->toBe('0249998888')
        ->and(session('auth.otp_channel'))->toBe('sms');
});

test('an already active account still gets the same reply and channel as an unknown number', function () {
    $active = User::factory()->create(['phone' => '0244002008', 'password' => bcrypt('Password@123')]);
    $active->assignRole('agent');

    $this->post('/activate', ['phone' => '0244002008'])
        ->assertRedirect('/verify-otp');

    expect(session('auth.otp_channel'))->toBe('sms')
        ->and(session('auth.login_identifier'))->toBe('0244002008');
});
