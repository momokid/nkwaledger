<?php

use App\Contracts\SmsProvider;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('an agent logging in with password gets a new-device alert immediately', function () {
    $agent = User::factory()->create([
        'phone' => '+233244000061',
        'password' => bcrypt('Password@123'),
    ]);
    $agent->assignRole('agent');

    $this->post('/login', [
        'identifier' => '+233244000061',
        'password' => 'Password@123',
    ]);

    expect(app(SmsProvider::class)->sentTo('+233244000061'))->toBeTrue();
});

test('a farmer logging in with password does not get any anomaly alert', function () {
    $farmer = User::factory()->create([
        'phone' => '+233244000062',
        'password' => bcrypt('Password@123'),
    ]);
    $farmer->assignRole('farmer');

    $this->post('/login', [
        'identifier' => '+233244000062',
        'password' => 'Password@123',
    ]);

    expect(app(SmsProvider::class)->sentTo('+233244000062'))->toBeFalse();
});

test('an admin completing otp login gets a new-device alert after verification', function () {
    $admin = User::factory()->create([
        'phone' => '+233244000060',
        'password' => bcrypt('Password@123'),
    ]);
    $admin->assignRole('admin');

    $this->post('/login', [
        'identifier' => '+233244000060',
        'password' => 'Password@123',
    ]);

    // replaces the auto-generated login otp with one whose plain code we know, without sending a second sms
    OtpCode::where('identifier', '+233244000060')->delete();
    OtpCode::create([
        'identifier' => '+233244000060',
        'code' => Hash::make('112233'),
        'type' => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->post('/verify-otp', [
        'identifier' => '+233244000060',
        'code' => '112233',
        'type' => 'login',
    ]);

    // two sms messages are expected to this number: the login otp code itself, and the new-device alert
    $sentToAdmin = collect(app(SmsProvider::class)->sent)->where('phone', '+233244000060');
    expect($sentToAdmin->count())->toBe(2);
});
