<?php

use App\Models\Kiosk;
use App\Models\Supplier;
use App\Models\User;

test('a guest cannot reach the supplier dashboard', function () {
    $this->get('/supplier/dashboard')->assertRedirect('/login');
});

test('a farmer cannot reach the supplier dashboard', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $this->actingAs($farmer)->get('/supplier/dashboard')->assertForbidden();
});

test('a supplier with no profile yet still sees a real dashboard, not the generic placeholder', function () {
    $user = User::factory()->create();
    $user->assignRole('supplier');

    $this->actingAs($user)->get('/supplier/dashboard')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Supplier/Dashboard')
            ->where('has_profile', false));
});

test('a supplier with a profile sees their kiosk count on the dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('supplier');
    $supplier = Supplier::factory()->verified()->create(['user_id' => $user->id]);
    Kiosk::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

    $this->actingAs($user)->get('/supplier/dashboard')
        ->assertInertia(fn($page) => $page
            ->where('has_profile', true)
            ->where('kiosks_count', 1));
});
