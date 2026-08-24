<?php

use App\Models\User;
use App\Models\OtpCode;
use App\Services\OtpService;
use App\Support\OtpOutcomeResolver;
use Illuminate\Support\Facades\Hash;

function pendingCode(string $phone, string $type, string $code = '112233'): array
{
    OtpCode::create([
        'identifier' => $phone,
        'code'       => Hash::make($code),
        'type'       => $type,
        'expires_at' => now()->addHour(),
    ]);

    return [
        'auth.login_identifier' => $phone,
        'auth.otp_type'         => $type,
    ];
}

test('a login code still logs the person in', function () {
    $user = User::factory()->create(['phone' => '0244000801']);
    $user->assignRole('agent');

    $this->withSession(pendingCode('0244000801', 'login'))
        ->post('/verify-otp', ['code' => '112233'])
        ->assertRedirect('/agent/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('an invitation code does not log the person in', function () {
    $user = User::factory()->unverified()->create(['phone' => '0244000802', 'password' => null]);
    $user->assignRole('agent');

    $this->withSession(pendingCode('0244000802', 'invitation'))
        ->post('/verify-otp', ['code' => '112233']);

    $this->assertGuest();
});

test('an invitation code sends them to set a password', function () {
    $user = User::factory()->unverified()->create(['phone' => '0244000803', 'password' => null]);
    $user->assignRole('vet');

    $this->withSession(pendingCode('0244000803', 'invitation'))
        ->post('/verify-otp', ['code' => '112233'])
        ->assertRedirect('/set-password');
});

// the next step needs to know who it is acting for, without trusting the browser
test('an invitation code leaves the account waiting in the session', function () {
    $user = User::factory()->unverified()->create(['phone' => '0244000804', 'password' => null]);
    $user->assignRole('adviser');

    $this->withSession(pendingCode('0244000804', 'invitation'))
        ->post('/verify-otp', ['code' => '112233']);

    expect(session('auth.activating_user_id'))->toBe($user->id);
});

test('an invitation code leaves the phone unverified until a password exists', function () {
    $user = User::factory()->unverified()->create(['phone' => '0244000805', 'password' => null]);
    $user->assignRole('supplier');

    $this->withSession(pendingCode('0244000805', 'invitation'))
        ->post('/verify-otp', ['code' => '112233']);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

test('a wrong invitation code changes nothing', function () {
    $user = User::factory()->unverified()->create(['phone' => '0244000806', 'password' => null]);
    $user->assignRole('agent');

    $this->withSession(pendingCode('0244000806', 'invitation'))
        ->post('/verify-otp', ['code' => '999999'])
        ->assertSessionHasErrors('code');

    expect(session('auth.activating_user_id'))->toBeNull();
    $this->assertGuest();
});

test('the pending code session is cleared either way', function () {
    $user = User::factory()->unverified()->create(['phone' => '0244000807', 'password' => null]);
    $user->assignRole('agent');

    $this->withSession(pendingCode('0244000807', 'invitation'))
        ->post('/verify-otp', ['code' => '112233']);

    expect(session('auth.login_identifier'))->toBeNull();
    expect(session('auth.otp_type'))->toBeNull();
});

// a type nobody decided the meaning of must not silently behave like a login
test('every otp type has an outcome defined', function () {
    $resolver = app(OtpOutcomeResolver::class);

    foreach (OtpService::TYPES as $type) {
        expect($resolver->authenticates($type))->toBeBool();
    }
});
