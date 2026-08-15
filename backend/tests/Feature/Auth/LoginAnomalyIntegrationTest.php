<?php

use App\Contracts\SmsProvider;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\UserKnownDevice;
use App\Services\Sms\FakeSmsProvider;
use Illuminate\Support\Facades\Hash;

// swaps the generated code for one this test knows, without sending a second sms
function replaceOtpWith(string $phone, string $code): void
{
    OtpCode::where('identifier', $phone)->delete();

    OtpCode::create([
        'identifier' => $phone,
        'code'       => Hash::make($code),
        'type'       => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);
}

test('an agent on an unknown device is sent to otp and is not logged in yet', function () {
    $agent = User::factory()->create([
        'phone'    => '+233244000061',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    $response = $this->post('/login', [
        'identifier' => '+233244000061',
        'password'   => 'Password@123',
    ]);

    $response->assertRedirect('/verify-otp');
    $this->assertGuest();
});

test('an agent device is not recorded until the code is verified', function () {
    $agent = User::factory()->create([
        'phone'    => '+233244000061',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    $this->post('/login', [
        'identifier' => '+233244000061',
        'password'   => 'Password@123',
    ]);

    expect(UserKnownDevice::where('user_id', $agent->id)->count())->toBe(0);
});

test('an agent completing otp is logged in and the device is recorded', function () {
    $agent = User::factory()->create([
        'phone'    => '+233244000061',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    $this->post('/login', [
        'identifier' => '+233244000061',
        'password'   => 'Password@123',
    ]);

    replaceOtpWith('+233244000061', '112233');

    $response = $this->post('/verify-otp', [
        'identifier' => '+233244000061',
        'code'       => '112233',
        'type'       => 'login',
    ]);

    $response->assertRedirect('/agent/dashboard');
    $this->assertAuthenticatedAs($agent);
    expect(UserKnownDevice::where('user_id', $agent->id)->count())->toBe(1);
});

test('an agent on a device already known logs in without otp', function () {
    $agent = User::factory()->create([
        'phone'    => '+233244000061',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    $this->post('/login', [
        'identifier' => '+233244000061',
        'password'   => 'Password@123',
    ]);
    replaceOtpWith('+233244000061', '112233');
    $this->post('/verify-otp', [
        'identifier' => '+233244000061',
        'code'       => '112233',
        'type'       => 'login',
    ]);
    $this->post('/logout');

    $response = $this->post('/login', [
        'identifier' => '+233244000061',
        'password'   => 'Password@123',
    ]);

    $response->assertRedirect('/agent/dashboard');
    $this->assertAuthenticatedAs($agent);
});

test('vet, adviser and supplier are all sent to otp on an unknown device', function () {
    $index = 70;

    foreach (['vet', 'adviser', 'supplier'] as $role) {
        $phone = '+2332440000' . $index++;

        $user = User::factory()->create([
            'phone'    => $phone,
            'password' => bcrypt('Password@123'),
        ]);
        $user->assignRole($role);

        $response = $this->post('/login', [
            'identifier' => $phone,
            'password'   => 'Password@123',
        ]);

        $response->assertRedirect('/verify-otp');
        $this->assertGuest();
    }
});

test('a farmer logs in straight away with no otp and no alert', function () {
    $farmer = User::factory()->create([
        'phone'    => '+233244000062',
        'password' => bcrypt('Password@123'),
    ]);
    $farmer->assignRole('farmer');

    $response = $this->post('/login', [
        'identifier' => '+233244000062',
        'password'   => 'Password@123',
    ]);

    $response->assertRedirect('/farmer/dashboard');
    $this->assertAuthenticatedAs($farmer);
    expect(app(SmsProvider::class)->sentTo('+233244000062'))->toBeFalse();
});

test('an admin completing otp login gets a new-device alert after verification', function () {
    $admin = User::factory()->create([
        'phone'    => '+233244000060',
        'password' => bcrypt('Password@123'),
    ]);
    $admin->assignRole('admin');

    $this->post('/login', [
        'identifier' => '+233244000060',
        'password'   => 'Password@123',
    ]);

    replaceOtpWith('+233244000060', '112233');

    $this->post('/verify-otp', [
        'identifier' => '+233244000060',
        'code'       => '112233',
        'type'       => 'login',
    ]);

    // two messages are expected: the login code itself, then the new-device alert
    $sentToAdmin = collect(app(SmsProvider::class)->sent)->where('phone', '+233244000060');
    expect($sentToAdmin->count())->toBe(2);
});
