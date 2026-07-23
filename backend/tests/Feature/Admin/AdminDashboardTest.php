<?php

use App\Models\User;

test('guests are redirected to login', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertRedirect('/login');
});

test('non-admin users cannot access the admin dashboard', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $response = $this->actingAs($farmer)->get('/admin/dashboard');

    $response->assertForbidden();
});

test('admins can access the admin dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertOk();
    $response->assertInertia(fn($page) => $page->component('Admin/Dashboard'));
});
