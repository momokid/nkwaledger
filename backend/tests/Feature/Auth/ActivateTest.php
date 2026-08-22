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
