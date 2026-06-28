<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'surname'    => 'Mensah',
            'first_name' => 'Kwame',
            'other_name' => 'Asante',
            'email'      => 'kwame@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Mensah', $user->surname);
    $this->assertSame('Kwame', $user->first_name);
    $this->assertSame('Asante', $user->other_name);
    $this->assertSame('kwame@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->withEmail()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'surname'    => $user->surname,
            'first_name' => $user->first_name,
            'email'      => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'Password@123',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'WrongPassword@123',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
