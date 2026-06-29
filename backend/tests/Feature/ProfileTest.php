<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/profile')->assertOk();
})->skip('Profile routes not yet implemented — deferred to Phase 2');

test('profile information can be updated', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patch('/profile', [
        'surname'    => 'Mensah',
        'first_name' => 'Kwame',
        'email'      => 'kwame@example.com',
    ])->assertSessionHasNoErrors()->assertRedirect('/profile');
})->skip('Profile routes not yet implemented — deferred to Phase 2');

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patch('/profile', [
        'surname'    => 'Mensah',
        'first_name' => 'Kwame',
        'email'      => $user->email,
    ])->assertSessionHasNoErrors()->assertRedirect('/profile');
})->skip('Profile routes not yet implemented — deferred to Phase 2');

test('user can delete their account', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->delete('/profile', [
        'password' => 'password',
    ])->assertSessionHasNoErrors()->assertRedirect('/');
})->skip('Profile routes not yet implemented — deferred to Phase 2');

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->delete('/profile', [
        'password' => 'WrongPassword@123',
    ])->assertSessionHasErrors('password')->assertRedirect('/profile');
})->skip('Profile routes not yet implemented — deferred to Phase 2');
