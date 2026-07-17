<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('otp verification screen can be rendered', function () {
    $response = $this->get('/verify-otp');

    $response->assertStatus(200);
});

test('user can verify a valid otp', function () {
    $user = User::factory()->create([
        'phone' => '+233244000001',
    ]);

    OtpCode::create([
        'identifier' => '+233244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
    ]);

    $response = $this->actingAs($user)->post('/verify-otp', [
        'identifier' => '+233244000001',
        'code'       => '123456',
        'type'       => 'registration',
    ]);

    $response->assertRedirect('/farmer/dashboard');
});

test('otp is marked as used after successful verification', function () {
    $user = User::factory()->create([
        'phone' => '+233244000001',
    ]);

    OtpCode::create([
        'identifier' => '+233244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($user)->post('/verify-otp', [
        'identifier' => '+233244000001',
        'code'       => '123456',
        'type'       => 'registration',
    ]);

    $this->assertDatabaseHas('otp_codes', [
        'identifier' => '+233244000001',
        'type'       => 'registration',
    ]);

    expect(OtpCode::where('identifier', '+233244000001')->first()->used_at)->not->toBeNull();
});

test('user phone is marked verified after successful otp verification', function () {
    $user = User::factory()->create([
        'phone'             => '+233244000001',
        'is_phone_verified' => false,
    ]);

    OtpCode::create([
        'identifier' => '+233244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($user)->post('/verify-otp', [
        'identifier' => '+233244000001',
        'code'       => '123456',
        'type'       => 'registration',
    ]);

    expect($user->fresh()->is_phone_verified)->toBeTrue();
});

test('expired otp cannot be verified', function () {
    $user = User::factory()->create([
        'phone' => '+233244000001',
    ]);

    OtpCode::create([
        'identifier' => '+233244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->subMinutes(10),
    ]);

    $response = $this->actingAs($user)->post('/verify-otp', [
        'identifier' => '+233244000001',
        'code'       => '123456',
        'type'       => 'registration',
    ]);

    $response->assertSessionHasErrors(['code']);
});

test('used otp cannot be verified again', function () {
    $user = User::factory()->create([
        'phone' => '+233244000001',
    ]);

    OtpCode::create([
        'identifier' => '+233244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
        'used_at'    => now(),
    ]);

    $response = $this->actingAs($user)->post('/verify-otp', [
        'identifier' => '+233244000001',
        'code'       => '123456',
        'type'       => 'registration',
    ]);

    $response->assertSessionHasErrors(['code']);
});

test('wrong otp increments attempts', function () {
    $user = User::factory()->create([
        'phone' => '+233244000001',
    ]);

    OtpCode::create([
        'identifier' => '+233244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($user)->post('/verify-otp', [
        'identifier' => '+233244000001',
        'code'       => '000000',
        'type'       => 'registration',
    ]);

    expect(OtpCode::where('identifier', '+233244000001')->first()->attempts)->toBe(1);
});

test('otp is voided after three wrong attempts', function () {
    $user = User::factory()->create([
        'phone' => '+233244000001',
    ]);

    OtpCode::create([
        'identifier' => '+233244000001',
        'code'       => Hash::make('123456'),
        'type'       => 'registration',
        'expires_at' => now()->addMinutes(5),
        'attempts'   => 2,
    ]);

    $response = $this->actingAs($user)->post('/verify-otp', [
        'identifier' => '+233244000001',
        'code'       => '000000',
        'type'       => 'registration',
    ]);

    $response->assertSessionHasErrors(['code']);

    expect(OtpCode::where('identifier', '+233244000001')->first()->attempts)->toBe(3);
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

    foreach ($roles as $role => $expectedRedirect) {
        $user = User::factory()->create([
            'phone' => '+23324400000' . (array_search($role, array_keys($roles)) + 1),
        ]);

        $user->assignRole($role);

        OtpCode::create([
            'identifier' => $user->phone,
            'code'       => Hash::make('123456'),
            'type'       => 'login',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->actingAs($user)->post('/verify-otp', [
            'identifier' => $user->phone,
            'code'       => '123456',
            'type'       => 'login',
        ]);

        $response->assertRedirect($expectedRedirect);
    }
});
