<?php

use App\Models\User;

test('a success message reaches the browser', function () {
    $response = $this->withSession(['success' => 'Invitation sent.'])->get('/login');

    $response->assertInertia(fn($page) => $page->where('flash.success', 'Invitation sent.'));
});

test('an error message reaches the browser', function () {
    $response = $this->withSession(['error' => 'We could not send that code.'])->get('/login');

    $response->assertInertia(fn($page) => $page->where('flash.error', 'We could not send that code.'));
});

test('a status message reaches the browser', function () {
    $response = $this->withSession(['status' => 'We sent a code to your phone.'])->get('/login');

    $response->assertInertia(fn($page) => $page->where('flash.status', 'We sent a code to your phone.'));
});

test('the three keys are null when nothing was flashed', function () {
    $response = $this->get('/login');

    $response->assertInertia(
        fn($page) => $page
            ->where('flash.success', null)
            ->where('flash.error', null)
            ->where('flash.status', null)
    );
});

test('a message flashed on a signed in page still arrives', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $response = $this->actingAs($user)
        ->withSession(['success' => 'Saved.'])
        ->get('/farmer/dashboard');

    $response->assertInertia(fn($page) => $page->where('flash.success', 'Saved.'));
});

// a real flash from a controller must show once and then be gone
test('a controller message shows once', function () {
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(Database\Seeders\PermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff.create');
    session(['auth.password_confirmed_at' => now()->timestamp]);

    $this->actingAs($admin)->from('/admin/dashboard')->post('/admin/staff', [
        'surname'    => 'Mensah',
        'first_name' => 'Kofi',
        'phone'      => '0244445566',
        'email'      => 'kofi@nkwaledger.com',
        'role'       => 'agent',
    ]);

    $this->actingAs($admin)->get('/admin/dashboard')
        ->assertInertia(fn($page) => $page->where('flash.success', 'Invitation sent to Kofi. They have an hour to activate the account.'));

    $this->actingAs($admin)->get('/admin/dashboard')
        ->assertInertia(fn($page) => $page->where('flash.success', null));
});
