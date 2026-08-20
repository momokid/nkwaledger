<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function invitedUser(string $phone = '0244000901', string $role = 'agent'): User
{
    $user = User::factory()->unverified()->create([
        'phone'    => $phone,
        'password' => null,
    ]);

    $user->assignRole($role);

    return $user;
}

test('the page needs a verified invitation behind it', function () {
    $this->get('/set-password')->assertRedirect('/login');
});

test('the page opens once the code has been verified', function () {
    $user = invitedUser();

    $this->withSession(['auth.activating_user_id' => $user->id])
        ->get('/set-password')
        ->assertOk();
});

test('a password cannot be set without a verified invitation', function () {
    $this->post('/set-password', [
        'password'              => 'Password@123',
        'password_confirmation' => 'Password@123',
    ])->assertRedirect('/login');
});

test('setting a password stores it', function () {
    $user = invitedUser();

    $this->withSession(['auth.activating_user_id' => $user->id])
        ->post('/set-password', [
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

    expect(Hash::check('Password@123', $user->fresh()->password))->toBeTrue();
});

// finishing activation is what proves the phone, not the code alone
test('the phone is verified once activation finishes', function () {
    $user = invitedUser();

    $this->withSession(['auth.activating_user_id' => $user->id])
        ->post('/set-password', [
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('they are signed in and sent to their dashboard', function () {
    $user = invitedUser('0244000902', 'vet');

    $response = $this->withSession(['auth.activating_user_id' => $user->id])
        ->post('/set-password', [
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

    $response->assertRedirect('/vet/dashboard');
    $this->assertAuthenticatedAs($user);
});

// the pass is single use, so a shared browser cannot replay it
test('the activation pass is spent once used', function () {
    $user = invitedUser();

    $this->withSession(['auth.activating_user_id' => $user->id])
        ->post('/set-password', [
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

    expect(session('auth.activating_user_id'))->toBeNull();
});

test('a weak password is refused', function (string $password) {
    $user = invitedUser();

    $this->withSession(['auth.activating_user_id' => $user->id])
        ->post('/set-password', [
            'password'              => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

    expect($user->fresh()->password)->toBeNull();
})->with(['abc12', '12345678', 'password']);

test('a mismatched confirmation is refused', function () {
    $user = invitedUser();

    $this->withSession(['auth.activating_user_id' => $user->id])
        ->post('/set-password', [
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@456',
        ])->assertSessionHasErrors('password');

    expect($user->fresh()->password)->toBeNull();
});

// a pass pointing at a deleted or already active account must not open anything
test('an account that already has a password cannot be reset this way', function () {
    $user = User::factory()->create(['phone' => '0244000903', 'password' => bcrypt('Existing@123')]);
    $user->assignRole('agent');

    $this->withSession(['auth.activating_user_id' => $user->id])
        ->post('/set-password', [
            'password'              => 'Attacker@123',
            'password_confirmation' => 'Attacker@123',
        ])->assertRedirect('/login');

    expect(Hash::check('Existing@123', $user->fresh()->password))->toBeTrue();
});

test('a pass pointing at nobody is refused', function () {
    $this->withSession(['auth.activating_user_id' => 99999])
        ->get('/set-password')
        ->assertRedirect('/login');
});
