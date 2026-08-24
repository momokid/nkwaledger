<?php

use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

test('registration stores a local number in international form', function () {
    $this->post('/register', [
        'surname'               => 'Mensah',
        'first_name'            => 'Kwame',
        'phone'                 => '0244445566',
        'password'              => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    expect(User::where('phone', '0244445566')->exists())->toBeTrue();
});

test('the registration code is sent to the clean number', function () {
    $this->post('/register', [
        'surname'               => 'Mensah',
        'first_name'            => 'Kwame',
        'phone'                 => '0244445566',
        'password'              => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    expect(OtpCode::where('identifier', '0244445566')->exists())->toBeTrue();
    expect(OtpCode::where('identifier', '+233244445566')->exists())->toBeFalse();
});

test('the same person cannot register twice in two spellings', function () {
    User::factory()->create(['phone' => '0244445566']);

    $response = $this->post('/register', [
        'surname'               => 'Boateng',
        'first_name'            => 'Ama',
        'phone'                 => '0244445566',
        'password'              => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    $response->assertSessionHasErrors('phone');
});

test('registration rejects a number that is not a ghanaian mobile', function (string $phone) {
    $response = $this->post('/register', [
        'surname'               => 'Mensah',
        'first_name'            => 'Kwame',
        'phone'                 => $phone,
        'password'              => 'Password@123',
        'password_confirmation' => 'Password@123',
    ]);

    $response->assertSessionHasErrors('phone');
})->with(['0302445566', '+2348012345566', '024444556', 'not a phone']);

test('a farmer registered internationally can log in locally', function () {
    User::factory()->create([
        'phone'    => '0244445566',
        'password' => bcrypt('Password@123'),
    ])->assignRole('farmer');

    $response = $this->post('/login', [
        'identifier' => '0244445566',
        'password'   => 'Password@123',
    ]);

    $response->assertRedirect('/farmer/dashboard');
    $this->assertAuthenticated();
});

test('a farmer registered locally can log in internationally', function () {
    User::factory()->create([
        'phone'    => '0209998877',
        'password' => bcrypt('Password@123'),
    ])->assignRole('farmer');

    $response = $this->post('/login', [
        'identifier' => '0209998877',
        'password'   => 'Password@123',
    ]);

    $this->assertAuthenticated();
});

test('email login is untouched by the normaliser', function () {
    User::factory()->withEmail()->create([
        'email'    => 'kwame@example.com',
        'password' => bcrypt('Password@123'),
    ])->assignRole('farmer');

    $this->post('/login', [
        'identifier' => 'kwame@example.com',
        'password'   => 'Password@123',
    ]);

    $this->assertAuthenticated();
});

// two spellings of one number must share a rate limit bucket, or five attempts becomes ten
test('failed attempts in two spellings share one throttle', function () {
    User::factory()->create([
        'phone'    => '0244445566',
        'password' => bcrypt('Password@123'),
    ]);

    for ($i = 0; $i < 3; $i++) {
        $this->post('/login', ['identifier' => '0244445566', 'password' => 'wrong']);
    }

    for ($i = 0; $i < 3; $i++) {
        $this->post('/login', ['identifier' => '0244445566', 'password' => 'wrong']);
    }

    $response = $this->post('/login', [
        'identifier' => '0244445566',
        'password'   => 'Password@123',
    ]);

    $response->assertSessionHasErrors('identifier');
    $this->assertGuest();
});

test('an otp login request finds an account written the other way', function () {
    User::factory()->create(['phone' => '0244445566']);

    $this->post('/login/otp', ['phone' => '0244445566']);

    expect(OtpCode::where('identifier', '0244445566')->where('type', 'login')->exists())->toBeTrue();
});

test('the otp session holds the clean number', function () {
    User::factory()->create(['phone' => '0244445566']);

    $this->post('/login/otp', ['phone' => '0244445566']);

    $this->assertEquals('0244445566', session('auth.login_identifier'));
});

test('a code requested locally verifies and logs the farmer in', function () {
    $user = User::factory()->create(['phone' => '0244445566']);
    $user->assignRole('farmer');

    $this->post('/login/otp', ['phone' => '0244445566']);

    OtpCode::where('identifier', '0244445566')->delete();
    OtpCode::create([
        'identifier' => '0244445566',
        'code'       => Illuminate\Support\Facades\Hash::make('112233'),
        'type'       => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->post('/verify-otp', ['code' => '112233']);

    $this->assertAuthenticatedAs($user);
});

test('an invite accepts the format an admin would type', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff.create');
    session(['auth.password_confirmed_at' => now()->timestamp]);

    $this->actingAs($admin)->post('/admin/staff', [
        'surname'    => 'Mensah',
        'first_name' => 'Kofi',
        'phone'      => '024 444 5566',
        'email'      => 'kofi@nkwaledger.com',
        'role'       => 'agent',
    ]);

    expect(User::where('phone', '0244445566')->exists())->toBeTrue();
});
