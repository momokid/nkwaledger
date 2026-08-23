
<?php

use App\Models\User;

function attempt(string $phone): Illuminate\Testing\TestResponse
{
    return test()->post('/login', [
        'identifier' => $phone,
        'password'   => 'Wrong@123',
    ]);
}

// trying many accounts once each never trips a per-account limit, which is how stuffing works
test('one ip is capped after twenty failed attempts across different accounts', function () {
    for ($i = 1; $i <= 20; $i++) {
        attempt('02440060' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }

    attempt('0244006099')->assertSessionHasErrors('identifier');
});

test('a blocked ip cannot log in even with the right password', function () {
    $user = User::factory()->create([
        'phone'    => '0244006101',
        'password' => bcrypt('Password@123'),
    ]);
    $user->assignRole('farmer');

    for ($i = 1; $i <= 20; $i++) {
        attempt('02440062' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }

    $this->post('/login', [
        'identifier' => '0244006101',
        'password'   => 'Password@123',
    ])->assertSessionHasErrors('identifier');

    $this->assertGuest();
});

// a successful sign in must not be punished, or a busy office locks itself out
test('a successful login does not count toward the limit', function () {
    $user = User::factory()->create([
        'phone'    => '0244006201',
        'password' => bcrypt('Password@123'),
    ]);
    $user->assignRole('farmer');

    for ($i = 1; $i <= 25; $i++) {
        $this->post('/login', [
            'identifier' => '0244006201',
            'password'   => 'Password@123',
        ]);
        $this->post('/logout');
    }

    $this->assertGuest();

    $this->post('/login', [
        'identifier' => '0244006201',
        'password'   => 'Password@123',
    ])->assertRedirect('/farmer/dashboard');
});

test('a handful of failed attempts is still allowed', function () {
    attempt('0244006301');
    attempt('0244006302');
    attempt('0244006303')->assertSessionHasErrors('identifier');
});
