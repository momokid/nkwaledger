<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a signed-in user can open the Help page', function () {
    $this->actingAs(User::factory()->create())->get('/help')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Help/Index'));
});

test('a signed-in user can open the Settings page', function () {
    $this->actingAs(User::factory()->create())->get('/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Index'));
});

test('a guest is sent to sign in from Help and Settings', function () {
    $this->get('/help')->assertRedirect('/login');
    $this->get('/settings')->assertRedirect('/login');
});
