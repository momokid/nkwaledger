<?php

use App\Models\User;

test('a guest is redirected to login when visiting the admin dashboard', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertRedirect('/login');
});

test('a farmer cannot access the admin dashboard', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $response = $this->actingAs($farmer)->get('/admin/dashboard');

    $response->assertForbidden();
});

test('an admin can access the admin dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertOk();
});
