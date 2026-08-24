<?php

use App\Models\AuditLog;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

function entriesFor(string $action)
{
    return AuditLog::where('action', $action);
}

test('a failed login is recorded', function () {
    User::factory()->create(['phone' => '0244007001', 'password' => bcrypt('Password@123')]);

    $this->post('/login', ['identifier' => '0244007001', 'password' => 'Wrong@123']);

    expect(entriesFor('login.failed')->exists())->toBeTrue();
});

// a failed attempt against a number with no account is the shape an attack takes
test('a failed login for an unknown number is recorded', function () {
    $this->post('/login', ['identifier' => '0249999999', 'password' => 'Wrong@123']);

    expect(entriesFor('login.failed')->exists())->toBeTrue();
});

test('a failed login records where it came from', function () {
    $this->post('/login', ['identifier' => '0249999999', 'password' => 'Wrong@123']);

    expect(entriesFor('login.failed')->first()->ip_address)->not->toBeNull();
});

// the number tried is the useful part, and it is not a secret the log can leak
test('a failed login records the number tried but never the password', function () {
    $this->post('/login', ['identifier' => '0249999999', 'password' => 'Hunter@123']);

    $entry = entriesFor('login.failed')->first();

    expect($entry->new_values['identifier'])->toBe('0249999999');
    expect(json_encode($entry->new_values))->not->toContain('Hunter@123');
});

// staff pass through otp first, so the entry belongs where the session actually starts
test('a staff member signing in is recorded once the code checks out', function (string $role) {
    $staff = User::factory()->create(['phone' => '0244007002', 'password' => bcrypt('Password@123')]);
    $staff->assignRole($role);

    $this->post('/login', ['identifier' => '0244007002', 'password' => 'Password@123']);

    OtpCode::where('identifier', '0244007002')->delete();
    OtpCode::create([
        'identifier' => '0244007002',
        'code'       => Hash::make('112233'),
        'type'       => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->post('/verify-otp', ['code' => '112233']);

    expect(entriesFor('login.succeeded')->where('user_id', $staff->id)->exists())->toBeTrue();
})->with(['admin', 'agent']);

// farmers sign in daily and would swamp a table meant for audits
test('a farmer signing in is not recorded here', function () {
    $farmer = User::factory()->create(['phone' => '0244007004', 'password' => bcrypt('Password@123')]);
    $farmer->assignRole('farmer');

    $this->post('/login', ['identifier' => '0244007004', 'password' => 'Password@123']);

    expect(entriesFor('login.succeeded')->where('user_id', $farmer->id)->exists())->toBeFalse();
});

test('a wrong otp code is recorded', function () {
    $user = User::factory()->create(['phone' => '0244007005']);

    OtpCode::create([
        'identifier' => '0244007005',
        'code'       => Hash::make('112233'),
        'type'       => 'login',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->withSession([
        'auth.login_identifier' => '0244007005',
        'auth.otp_type'         => 'login',
    ])->post('/verify-otp', ['code' => '999999']);

    expect(entriesFor('otp.failed')->exists())->toBeTrue();
});

test('disabling an account is recorded', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo(['staff.view', 'staff.update']);

    $staff = User::factory()->create(['phone' => '0244007006', 'password' => bcrypt('Password@123')]);
    $staff->assignRole('agent');

    $this->actingAs($admin)->patch("/admin/staff/{$staff->id}/disable");

    $entry = entriesFor('staff.disabled')->first();

    expect($entry)->not->toBeNull();
    expect($entry->user_id)->toBe($admin->id);
    expect($entry->auditable_id)->toBe($staff->id);
});

test('cancelling an invitation is recorded', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo(['staff.view', 'staff.delete']);

    $pending = User::factory()->unverified()->create(['phone' => '0244007007', 'password' => null]);
    $pending->assignRole('agent');

    $this->actingAs($admin)->delete("/admin/staff/{$pending->id}");

    expect(entriesFor('staff.invitation_cancelled')->exists())->toBeTrue();
});

// the account is gone, so the entry has to stand on its own
test('a cancelled invitation entry survives the account being deleted', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo(['staff.view', 'staff.delete']);

    $pending = User::factory()->unverified()->create(['phone' => '0244007008', 'password' => null]);
    $pending->assignRole('agent');

    $this->actingAs($admin)->delete("/admin/staff/{$pending->id}");

    $entry = entriesFor('staff.invitation_cancelled')->first();

    expect($entry->old_values['phone'])->toBe('0244007008');
});
