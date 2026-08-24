<?php

use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo(['staff.view', 'staff.create', 'staff.update', 'staff.delete']);
});

function managedStaff(string $phone, bool $activated = true, string $role = 'agent'): User
{
    $user = User::factory()->create([
        'phone'    => $phone,
        'password' => $activated ? bcrypt('Password@123') : null,
    ]);

    $user->assignRole($role);

    return $user;
}

test('an active account can be disabled', function () {
    $staff = managedStaff('0244003001');

    $this->actingAs($this->admin)->patch("/admin/staff/{$staff->id}/disable")
        ->assertSessionDoesntHaveErrors();

    expect($staff->fresh()->is_active)->toBeFalse();
});

test('a disabled account can be enabled again', function () {
    $staff = managedStaff('0244003002');
    $staff->update(['is_active' => false]);

    $this->actingAs($this->admin)->patch("/admin/staff/{$staff->id}/enable")
        ->assertSessionDoesntHaveErrors();

    expect($staff->fresh()->is_active)->toBeTrue();
});

// disabling is checked at login, so it locks them out straight away
test('a disabled account cannot log in', function () {
    $staff = managedStaff('0244003003');

    $this->actingAs($this->admin)->patch("/admin/staff/{$staff->id}/disable");

    $this->post('/logout');

    $this->post('/login', [
        'identifier' => '0244003003',
        'password'   => 'Password@123',
    ])->assertSessionHasErrors('identifier');

    $this->assertGuest();
});

test('disabling needs the update permission', function () {
    $staff = managedStaff('0244003004');

    $viewer = User::factory()->create();
    $viewer->assignRole('agent');
    $viewer->givePermissionTo('staff.view');

    $this->actingAs($viewer)->patch("/admin/staff/{$staff->id}/disable")->assertForbidden();
});

test('an admin cannot disable their own account', function () {
    $this->actingAs($this->admin)->patch("/admin/staff/{$this->admin->id}/disable")
        ->assertForbidden();

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

test('a pending invitation can be cancelled', function () {
    $pending = managedStaff('0244003005', activated: false);

    $this->actingAs($this->admin)->delete("/admin/staff/{$pending->id}")
        ->assertSessionDoesntHaveErrors();

    expect(User::find($pending->id))->toBeNull();
});

// a cancelled invitation must not leave a code that still works
test('cancelling clears any outstanding code', function () {
    $pending = managedStaff('0244003006', activated: false);

    OtpCode::create([
        'identifier' => '0244003006',
        'code'       => bcrypt('112233'),
        'type'       => 'invitation',
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($this->admin)->delete("/admin/staff/{$pending->id}");

    expect(OtpCode::where('identifier', '0244003006')->exists())->toBeFalse();
});

// an activated person may already have records against their name
test('an activated account cannot be cancelled', function () {
    $staff = managedStaff('0244003007');

    $this->actingAs($this->admin)->delete("/admin/staff/{$staff->id}")
        ->assertSessionHasErrors();

    expect(User::find($staff->id))->not->toBeNull();
});

test('cancelling needs the delete permission', function () {
    $pending = managedStaff('0244003008', activated: false);

    $viewer = User::factory()->create();
    $viewer->assignRole('agent');
    $viewer->givePermissionTo(['staff.view', 'staff.update']);

    $this->actingAs($viewer)->delete("/admin/staff/{$pending->id}")->assertForbidden();
});

test('the list says what this admin may do', function () {
    $this->actingAs($this->admin)->get('/admin/staff')
        ->assertInertia(
            fn($page) => $page
                ->where('permissions.update', true)
                ->where('permissions.delete', true)
        );
});
