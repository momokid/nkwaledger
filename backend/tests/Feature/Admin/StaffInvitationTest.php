<?php

use App\Contracts\SmsProvider;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo('staff.create');

    session(['auth.password_confirmed_at' => now()->timestamp]);
});

function staffPayload(array $overrides = []): array
{
    return array_merge([
        'surname'    => 'Mensah',
        'first_name' => 'Kofi',
        'other_name' => null,
        'phone'      => '0244000501',
        'email'      => 'kofi.mensah@nkwaledger.com',
        'role'       => 'agent',
    ], $overrides);
}

test('a guest is redirected to login', function () {
    $response = $this->post('/admin/staff', staffPayload());

    $response->assertRedirect('/login');
});

test('a user without staff.create is forbidden', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    $response = $this->actingAs($agent)->post('/admin/staff', staffPayload());

    $response->assertForbidden();
});

test('an authorized admin creates the account', function () {
    $response = $this->actingAs($this->admin)->post('/admin/staff', staffPayload());

    $response->assertSessionDoesntHaveErrors();

    $staff = User::where('phone', '0244000501')->first();

    expect($staff)->not->toBeNull();
    expect($staff->first_name)->toBe('Kofi');
    expect($staff->hasRole('agent'))->toBeTrue();
});

test('the account starts with no password', function () {
    $this->actingAs($this->admin)->post('/admin/staff', staffPayload());

    expect(User::where('phone', '0244000501')->first()->password)->toBeNull();
});

test('a password sent in the form is ignored', function () {
    $this->actingAs($this->admin)->post('/admin/staff', staffPayload([
        'password' => 'Attacker@123',
    ]));

    expect(User::where('phone', '0244000501')->first()->password)->toBeNull();
});

test('the account starts unverified', function () {
    $this->actingAs($this->admin)->post('/admin/staff', staffPayload());

    expect(User::where('phone', '0244000501')->first()->phone_verified_at)->toBeNull();
});

test('an invitation code is issued', function () {
    $this->actingAs($this->admin)->post('/admin/staff', staffPayload());

    $otp = OtpCode::where('identifier', '0244000501')
        ->where('type', 'invitation')
        ->first();

    expect($otp)->not->toBeNull();
    expect(now()->diffInMinutes($otp->expires_at))->toBeGreaterThan(50);
});

test('one sms carries the invitation', function () {
    $this->actingAs($this->admin)->post('/admin/staff', staffPayload());

    expect(collect(app(SmsProvider::class)->sent)->where('phone', '0244000501')->count())->toBe(1);
});

test('each staff role can be invited', function (string $role) {
    $phone = '02440005' . random_int(10, 99);

    $this->actingAs($this->admin)->post('/admin/staff', staffPayload([
        'phone' => $phone,
        'email' => $role . '@nkwaledger.com',
        'role'  => $role,
    ]))->assertSessionDoesntHaveErrors();

    expect(User::where('phone', $phone)->first()->hasRole($role))->toBeTrue();
})->with(['agent', 'vet', 'adviser', 'supplier']);

test('the farmer role cannot be invited', function () {
    $response = $this->actingAs($this->admin)->post('/admin/staff', staffPayload([
        'role' => 'farmer',
    ]));

    $response->assertSessionHasErrors('role');
    expect(User::where('phone', '0244000501')->exists())->toBeFalse();
});

test('the admin role cannot be invited', function () {
    $response = $this->actingAs($this->admin)->post('/admin/staff', staffPayload([
        'role' => 'admin',
    ]));

    $response->assertSessionHasErrors('role');
});

test('a phone already on file is rejected', function () {
    User::factory()->create(['phone' => '0244000501']);

    $response = $this->actingAs($this->admin)->post('/admin/staff', staffPayload());

    $response->assertSessionHasErrors('phone');
    expect(app(SmsProvider::class)->sentTo('0244000501'))->toBeFalse();
});

test('an email already on file is rejected', function () {
    User::factory()->create(['email' => 'kofi.mensah@nkwaledger.com']);

    $response = $this->actingAs($this->admin)->post('/admin/staff', staffPayload());

    $response->assertSessionHasErrors('email');
});

test('a stale password confirmation blocks the request', function () {
    session()->forget('auth.password_confirmed_at');

    $response = $this->actingAs($this->admin)->post('/admin/staff', staffPayload());

    $response->assertRedirect('/confirm-password');
});
