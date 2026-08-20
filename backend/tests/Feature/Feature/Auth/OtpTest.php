<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// stands in for the login or registration step that sets these
function otpSession(string $identifier, string $type): array
{
    return [
        'auth.login_identifier' => $identifier,
        'auth.otp_type'         => $type,
    ];
}

test('user can verify a valid otp', function () {
    User::factory()->create(['phone' => '0244000001'])->assignRole('farmer');

    OtpCode::create([
        'identifier' => '0244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
    ]);

    $response = $this->withSession(otpSession('0244000001', 'registration'))
        ->post('/verify-otp', ['code' => '123456']);

    $response->assertRedirect('/farmer/dashboard');
});

test('otp is marked as used after successful verification', function () {
    User::factory()->create(['phone' => '0244000001'])->assignRole('farmer');

    OtpCode::create([
        'identifier' => '0244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->withSession(otpSession('0244000001', 'registration'))
        ->post('/verify-otp', ['code' => '123456']);

    expect(OtpCode::where('identifier', '0244000001')->first()->used_at)->not->toBeNull();
});

test('expired otp cannot be verified', function () {
    User::factory()->create(['phone' => '0244000001'])->assignRole('farmer');

    OtpCode::create([
        'identifier' => '0244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->subMinutes(10),
    ]);

    $response = $this->withSession(otpSession('0244000001', 'registration'))
        ->post('/verify-otp', ['code' => '123456']);

    $response->assertSessionHasErrors(['code']);
});

test('used otp cannot be verified again', function () {
    User::factory()->create(['phone' => '0244000001'])->assignRole('farmer');

    OtpCode::create([
        'identifier' => '0244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
        'used_at'    => now(),
    ]);

    $response = $this->withSession(otpSession('0244000001', 'registration'))
        ->post('/verify-otp', ['code' => '123456']);

    $response->assertSessionHasErrors(['code']);
});

test('wrong otp increments attempts', function () {
    User::factory()->create(['phone' => '0244000001'])->assignRole('farmer');

    OtpCode::create([
        'identifier' => '0244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->withSession(otpSession('0244000001', 'registration'))
        ->post('/verify-otp', ['code' => '000000']);

    expect(OtpCode::where('identifier', '0244000001')->first()->attempts)->toBe(1);
});

test('otp is voided after three wrong attempts', function () {
    User::factory()->create(['phone' => '0244000001'])->assignRole('farmer');

    OtpCode::create([
        'identifier' => '0244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
        'attempts'   => 2,
    ]);

    $response = $this->withSession(otpSession('0244000001', 'registration'))
        ->post('/verify-otp', ['code' => '000000']);

    $response->assertSessionHasErrors(['code']);

    expect(OtpCode::where('identifier', '0244000001')->first()->attempts)->toBe(3);
});

test('verified user is redirected to correct dashboard by role', function () {
    $roles = [
        'admin'    => '/admin/dashboard',
        'agent'    => '/agent/dashboard',
        'farmer'   => '/farmer/dashboard',
        'vet'      => '/vet/dashboard',
        'adviser'  => '/adviser/dashboard',
        'supplier' => '/supplier/dashboard',
    ];

    $index = 1;

    foreach ($roles as $role => $expectedRedirect) {
        $phone = '024400000' . $index++;

        $user = User::factory()->create(['phone' => $phone]);
        $user->assignRole($role);

        OtpCode::create([
            'identifier' => $phone,
            'code'       => Hash::make('123456'),
            'type'       => 'login',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->withSession(otpSession($phone, 'login'))
            ->post('/verify-otp', ['code' => '123456']);

        $response->assertRedirect($expectedRedirect);
    }
});
