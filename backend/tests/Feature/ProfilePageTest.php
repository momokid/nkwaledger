<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the profile page shows the user their own name, phone and email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.name', trim($user->first_name.' '.$user->surname))
            ->where('profile.phone', $user->phone)
            ->where('profile.email', $user->email)
            ->missing('profile.id'));
});

test('the profile page cannot change identity', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', ['email' => 'new@example.com'])->assertStatus(405);
    $this->actingAs($user)->delete('/profile')->assertStatus(405);
});

test('a guest is sent to sign in', function () {
    $this->get('/profile')->assertRedirect('/login');
});
