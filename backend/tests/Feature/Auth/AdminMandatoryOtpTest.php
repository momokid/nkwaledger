<?php

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

test('an admin logging in with the correct password is not authenticated yet and is sent to verify otp', function () {
    $admin = User::factory()->create([
        'phone' => '+233244000050',
        'password' => bcrypt('Password@123'),
    ]);
    $admin->assignRole('admin');

    $response = $this->post('/login', [
        'identifier' => '+233244000050',
        'password' => 'Password@123',
    ]);

    $response->assertRedirect('/verify-otp');
    $this->assertGuest();
});

test('an admin logging in generates a login otp tied to their phone', function () {
    $admin = User::factory()->create([
        'phone' => '+233244000051',
        'password' => bcrypt('Password@123'),
    ]);
    $admin->assignRole('admin');

    $this->post('/login', [
        'identifier' => '+233244000051',
        'password' => 'Password@123',
    ]);

    $this->assertDatabaseHas('otp_codes', [
        'identifier' => '+233244000051',
        'type' => 'login',
    ]);
});

test('an admin becomes authenticated only after completing otp', function () {
    $admin = User::factory()->create([
        'phone' => '+233244000052',
        'password' => bcrypt('Password@123'),
    ]);
    $admin->assignRole('admin');

    $this->post('/login', [
        'identifier' => '+233244000052',
        'password' => 'Password@123',
    ]);

    // clears the auto-generated code from /login so only our known code exists, avoiding any ambiguity in which row gets picked
    OtpCode::where('identifier', '+233244000052')->delete();

    OtpCode::create([
        'identifier' => '+233244000052',
        'code' => Hash::make('654321'),
        'type' => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);

    $response = $this->post('/verify-otp', [
        'identifier' => '+233244000052',
        'code' => '654321',
        'type' => 'login',
    ]);

    $response->assertRedirect('/admin/dashboard');
    $this->assertAuthenticated();
    expect(Auth::user()->id)->toBe($admin->id);
});

test('a farmer logging in with the correct password is authenticated immediately, no otp required', function () {
    $farmer = User::factory()->create([
        'phone' => '+233244000053',
        'password' => bcrypt('Password@123'),
    ]);
    $farmer->assignRole('farmer');

    $response = $this->post('/login', [
        'identifier' => '+233244000053',
        'password' => 'Password@123',
    ]);

    $response->assertRedirect('/farmer/dashboard');
    $this->assertAuthenticated();
});

test('an admin login still fails with a wrong password before any otp is generated', function () {
    $admin = User::factory()->create([
        'phone' => '+233244000054',
        'password' => bcrypt('Password@123'),
    ]);
    $admin->assignRole('admin');

    $response = $this->post('/login', [
        'identifier' => '+233244000054',
        'password' => 'WrongPassword@123',
    ]);

    $response->assertSessionHasErrors(['identifier']);
    $this->assertGuest();
    $this->assertDatabaseMissing('otp_codes', ['identifier' => '+233244000054']);
});
