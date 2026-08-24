<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function pendingStaff(string $phone = '0244001201', string $role = 'agent'): User
{
    $user = User::factory()->unverified()->create([
        'phone'    => $phone,
        'password' => null,
    ]);

    $user->assignRole($role);

    return $user;
}

function liveLoginCode(string $phone, string $code = '112233'): array
{
    OtpCode::create([
        'identifier' => $phone,
        'code'       => Hash::make($code),
        'type'       => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);

    return ['auth.login_identifier' => $phone, 'auth.otp_type' => 'login'];
}

// an unactivated account must not slip past activation through the ordinary login code
test('an otp login does not sign in an account with no password', function () {
    pendingStaff();

    $this->withSession(liveLoginCode('0244001201'))
        ->post('/verify-otp', ['code' => '112233']);

    $this->assertGuest();
});

test('an otp login sends them to finish activating instead', function () {
    pendingStaff();

    $this->withSession(liveLoginCode('0244001201'))
        ->post('/verify-otp', ['code' => '112233'])
        ->assertRedirect('/set-password');
});

test('the account is handed to the set password step', function () {
    $user = pendingStaff();

    $this->withSession(liveLoginCode('0244001201'))
        ->post('/verify-otp', ['code' => '112233']);

    expect(session('auth.activating_user_id'))->toBe($user->id);
});

test('their phone stays unverified until they finish', function () {
    $user = pendingStaff();

    $this->withSession(liveLoginCode('0244001201'))
        ->post('/verify-otp', ['code' => '112233']);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

// hashing against a null password would crash, so it must read as a plain failure
test('a password login against an account with no password is refused', function () {
    pendingStaff('0244001202');

    $response = $this->post('/login', [
        'identifier' => '0244001202',
        'password'   => 'Password@123',
    ]);

    $response->assertSessionHasErrors('identifier');
    $this->assertGuest();
});

test('an account with no password does not leak that it exists', function () {
    pendingStaff('0244001203');

    $known = $this->post('/login', [
        'identifier' => '0244001203',
        'password'   => 'Password@123',
    ]);

    $unknown = $this->post('/login', [
        'identifier' => '0249999999',
        'password'   => 'Password@123',
    ]);

    $known->assertSessionHasErrors('identifier');
    $unknown->assertSessionHasErrors('identifier');

    expect($known->getSession()->get('errors')->getBag('default')->first('identifier'))
        ->toBe($unknown->getSession()->get('errors')->getBag('default')->first('identifier'));
});

test('an activated account still logs in normally', function () {
    $user = User::factory()->create([
        'phone'    => '0244001204',
        'password' => bcrypt('Password@123'),
    ]);
    $user->assignRole('farmer');

    $this->post('/login', [
        'identifier' => '0244001204',
        'password'   => 'Password@123',
    ]);

    $this->assertAuthenticatedAs($user);
});
