<?php

use App\Contracts\SmsProvider;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo(['staff.view', 'staff.create']);
});

function staffMember(string $phone, string $role = 'agent', bool $activated = true): User
{
    $user = User::factory()->create([
        'phone'    => $phone,
        'password' => $activated ? bcrypt('Password@123') : null,
    ]);

    $user->assignRole($role);

    return $user;
}

test('a guest is redirected to login', function () {
    $this->get('/admin/staff')->assertRedirect('/login');
});

test('a user without staff.view is forbidden', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    $this->actingAs($agent)->get('/admin/staff')->assertForbidden();
});

test('an authorized admin sees the page', function () {
    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/Staff/Index'));
});

test('staff accounts are listed', function () {
    staffMember('0244002001', 'agent');
    staffMember('0244002002', 'vet');

    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertInertia(fn($page) => $page->has('staff.data', 2));
});

// farmers are a different population entirely and would swamp this list
test('farmers are left out', function () {
    staffMember('0244002003', 'agent');

    $farmer = User::factory()->create(['phone' => '0244002004']);
    $farmer->assignRole('farmer');

    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertInertia(fn($page) => $page->has('staff.data', 1));
});

test('admins are left out', function () {
    staffMember('0244002005', 'agent');

    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertInertia(fn($page) => $page->has('staff.data', 1));
});

test('each row carries the role', function () {
    staffMember('0244002006', 'supplier');

    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertInertia(fn($page) => $page->where('staff.data.0.role', 'supplier'));
});

// an admin needs to see at a glance who never finished activating
test('an account with no password reads as pending', function () {
    staffMember('0244002007', 'vet', activated: false);

    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertInertia(fn($page) => $page->where('staff.data.0.is_activated', false));
});

test('an account with a password reads as activated', function () {
    staffMember('0244002008', 'vet');

    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertInertia(fn($page) => $page->where('staff.data.0.is_activated', true));
});

test('the password never reaches the browser', function () {
    staffMember('0244002009', 'agent');

    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertInertia(fn($page) => $page->missing('staff.data.0.password'));
});

test('the page says whether this admin may invite', function () {
    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertInertia(fn($page) => $page->where('permissions.create', true));
});

test('a viewer without staff.create is told they cannot invite', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('agent');
    $viewer->givePermissionTo('staff.view');

    $this->actingAs($viewer)->get('/admin/staff')
        ->assertInertia(fn($page) => $page->where('permissions.create', false));
});

test('the list is paginated', function () {
    User::factory()->count(20)->create()->each(fn($user) => $user->assignRole('agent'));

    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertInertia(fn($page) => $page->has('staff.data', 15));
});

test('an invite can be resent to someone still pending', function () {
    $pending = staffMember('0244002010', 'agent', activated: false);

    $this->actingAs($this->admin)->post("/admin/staff/{$pending->id}/resend")
        ->assertSessionDoesntHaveErrors();

    expect(OtpCode::where('identifier', '0244002010')->where('type', 'invitation')->exists())->toBeTrue();
    expect(app(SmsProvider::class)->sentTo('0244002010'))->toBeTrue();
});

// an active account has nothing to activate, so a resend would just be a free sms
test('an invite cannot be resent to an active account', function () {
    $active = staffMember('0244002011', 'agent');

    $this->actingAs($this->admin)->post("/admin/staff/{$active->id}/resend")
        ->assertSessionHasErrors();

    expect(app(SmsProvider::class)->sentTo('0244002011'))->toBeFalse();
});

test('resending needs the invite permission', function () {
    $pending = staffMember('0244002012', 'agent', activated: false);

    $viewer = User::factory()->create();
    $viewer->assignRole('agent');
    $viewer->givePermissionTo('staff.view');

    $this->actingAs($viewer)->post("/admin/staff/{$pending->id}/resend")->assertForbidden();
});
