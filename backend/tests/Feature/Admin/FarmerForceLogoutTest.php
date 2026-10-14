<?php

require_once __DIR__ . '/../../Support/OrphanedFarmer.php';

use App\Models\AuditLog;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Models\UserPermissionDenial;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    // the real session table, not the in-memory one the tests normally use
    config(['session.driver' => 'database']);
    app('session')->forgetDrivers();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->farmerUser = User::factory()->create(['phone' => '0244000701']);
    $this->farmerUser->assignRole('farmer');
    $this->farmer = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

function farmerSessionFor(User $user): string
{
    $id = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test phone',
        'payload' => base64_encode(json_encode(['_token' => Str::random(40), Auth::guard('web')->getName() => $user->id])),
        'last_activity' => time(),
    ]);

    return $id;
}

// a request carrying only these cookies, as a phone that kept them would send it
function farmerPhone(array $cookies)
{
    app('auth')->forgetGuards();
    app('session.store')->flush();

    return test()->withCredentials()->withCookies($cookies);
}

function forceLogoutFarmer(User $admin, FarmerProfile|string $farmer)
{
    app('auth')->forgetGuards();
    $uuid = $farmer instanceof FarmerProfile ? $farmer->uuid : $farmer;

    return test()->actingAs($admin)->postJson("/admin/farmers/{$uuid}/force-logout");
}

// a farmer profile whose account is gone: the constraint is deferred inside the test's own transaction
function farmerWithoutLogin(): FarmerProfile
{
    return orphanFarmerProfile(FarmerProfile::factory()->create());
}

function denyPermission(User $user, string $permission): void
{
    UserPermissionDenial::create([
        'user_id' => $user->id,
        'permission_id' => Permission::where('name', $permission)->value('id'),
        'denied_by' => $user->id,
        'reason' => 'test',
    ]);
}

test('the farmer\'s old session stops working, and their old remember-me token with it', function () {
    $name = config('session.cookie');
    $session = farmerSessionFor($this->farmerUser);
    $this->farmerUser->forceFill(['remember_token' => Str::random(60)])->save();
    $recaller = "{$this->farmerUser->id}|{$this->farmerUser->fresh()->remember_token}|{$this->farmerUser->password}";
    $recallerName = Auth::guard('web')->getRecallerName();

    farmerPhone([$name => $session])->getJson('/auth/check')->assertOk();
    farmerPhone([$recallerName => $recaller])->getJson('/auth/check')->assertOk();

    forceLogoutFarmer($this->admin, $this->farmer)->assertOk();

    expect(DB::table('sessions')->where('user_id', $this->farmerUser->id)->count())->toBe(0)
        ->and($this->farmerUser->fresh()->remember_token)->not->toBe($this->farmerUser->remember_token);
    farmerPhone([$name => $session])->getJson('/auth/check')->assertStatus(302);
    farmerPhone([$recallerName => $recaller])->getJson('/auth/check')->assertStatus(302);
});

test('the farmer can sign in again afterwards', function () {
    forceLogoutFarmer($this->admin, $this->farmer)->assertOk();

    app('auth')->forgetGuards();
    $this->post('/login', ['identifier' => '0244000701', 'password' => 'Password@123'])->assertRedirect();

    expect($this->farmerUser->fresh()->is_active)->toBeTrue();

    $fresh = farmerSessionFor($this->farmerUser->fresh());
    farmerPhone([config('session.cookie') => $fresh])->getJson('/auth/check')->assertOk();
});

test('an old session cannot send saved records, and a new one can', function () {
    $old = farmerSessionFor($this->farmerUser);
    forceLogoutFarmer($this->admin, $this->farmer)->assertOk();
    $name = config('session.cookie');

    farmerPhone([$name => $old])->postJson('/sync/submissions', ['records' => []])->assertUnauthorized();

    $fresh = farmerSessionFor($this->farmerUser);
    farmerPhone([$name => $fresh])->postJson('/sync/submissions', ['records' => []])->assertStatus(422);
});

test('an admin who is also a farmer cannot sign themselves out this way', function () {
    $both = User::factory()->create();
    $both->assignRole('admin');
    $both->assignRole('farmer');
    $own = FarmerProfile::factory()->create(['user_id' => $both->id]);
    farmerSessionFor($both);

    forceLogoutFarmer($both, $own)->assertForbidden();

    expect(DB::table('sessions')->where('user_id', $both->id)->count())->toBeGreaterThanOrEqual(1)
        ->and(AuditLog::where('action', 'farmer.forced_logout')->count())->toBe(0);
});

test('a user without the farmer permission is refused and nothing is touched', function () {
    farmerSessionFor($this->farmerUser);
    denyPermission($this->admin, 'farmers.force-logout');

    forceLogoutFarmer($this->admin, $this->farmer)->assertForbidden();

    expect(DB::table('sessions')->where('user_id', $this->farmerUser->id)->count())->toBe(1);
});

test('holding only the staff force-logout permission does not allow farmers', function () {
    farmerSessionFor($this->farmerUser);
    $staffOnly = User::factory()->create();
    $staffOnly->assignRole('admin');
    denyPermission($staffOnly, 'farmers.force-logout');

    expect(app(\App\Services\AccessControlService::class)->can($staffOnly, 'staff.force-logout'))->toBeTrue();

    forceLogoutFarmer($staffOnly, $this->farmer)->assertForbidden();

    expect(DB::table('sessions')->where('user_id', $this->farmerUser->id)->count())->toBe(1);
});

test('an agent, even one handed the permission, cannot use this route', function () {
    farmerSessionFor($this->farmerUser);
    $agent = User::factory()->create();
    $agent->assignRole('agent');
    $agent->givePermissionTo('farmers.force-logout');
    $this->farmer->update(['assigned_agent_id' => $agent->id]);

    forceLogoutFarmer($agent, $this->farmer)->assertForbidden();

    expect(DB::table('sessions')->where('user_id', $this->farmerUser->id)->count())->toBe(1);
});

test('an account that is not a farmer is refused even if it carries a farmer profile', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');
    $stray = FarmerProfile::factory()->create(['user_id' => $agent->id]);
    farmerSessionFor($agent);

    forceLogoutFarmer($this->admin, $stray)->assertForbidden();

    expect(DB::table('sessions')->where('user_id', $agent->id)->count())->toBe(1);
});

test('staff and admin accounts cannot be reached on the farmer route', function () {
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');
    $agent = User::factory()->create();
    $agent->assignRole('agent');
    farmerSessionFor($otherAdmin);
    farmerSessionFor($agent);

    forceLogoutFarmer($this->admin, $otherAdmin->uuid)->assertNotFound();
    forceLogoutFarmer($this->admin, $agent->uuid)->assertNotFound();

    expect(DB::table('sessions')->whereIn('user_id', [$otherAdmin->id, $agent->id])->count())->toBe(2);
});

test('a farmer with no linked login is refused with the server\'s own message', function () {
    $orphan = farmerWithoutLogin();

    $response = forceLogoutFarmer($this->admin, $orphan)->assertStatus(409);

    expect($response->json('message'))->toBe('Conflict')
        ->and(AuditLog::where('action', 'farmer.forced_logout')->count())->toBe(0);
});

test('each use is audit logged with who, which farmer and when, and nothing sensitive', function () {
    farmerSessionFor($this->farmerUser);

    forceLogoutFarmer($this->admin, $this->farmer)->assertOk();

    $entry = AuditLog::where('action', 'farmer.forced_logout')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($this->admin->id)
        ->and($entry->auditable_id)->toBe($this->farmer->id)
        ->and($entry->auditable_type)->toBe(FarmerProfile::class)
        ->and($entry->created_at)->not->toBeNull()
        ->and($entry->old_values)->toBeNull()
        ->and($entry->new_values)->toBeNull()
        ->and(AuditLog::where('action', 'staff.forced_logout')->count())->toBe(0);
});

test('the action is limited per admin', function () {
    for ($i = 0; $i < 10; $i++) {
        forceLogoutFarmer($this->admin, $this->farmer)->assertOk();
    }

    forceLogoutFarmer($this->admin, $this->farmer)->assertStatus(429);

    $second = User::factory()->create();
    $second->assignRole('admin');
    forceLogoutFarmer($second, $this->farmer)->assertOk();
});

test('the answer carries no numeric id', function () {
    $body = forceLogoutFarmer($this->admin, $this->farmer)->assertOk()->getContent();

    expect($body)->not->toContain('"id"');
});

test('the farmer list tells the page who may be signed out, and who has no login to end', function () {
    $orphan = farmerWithoutLogin();
    app('auth')->forgetGuards();

    $this->actingAs($this->admin)->get('/admin/farmers')->assertOk()->assertInertia(fn($page) => $page
        ->where('permissions.force_logout', true)
        ->where('farmers.data', fn($rows) => collect($rows)->firstWhere('id', $this->farmer->uuid)['has_login'] === true
            && collect($rows)->firstWhere('id', $orphan->uuid)['has_login'] === false));

    denyPermission($this->admin, 'farmers.force-logout');
    app('auth')->forgetGuards();

    $this->actingAs($this->admin)->get('/admin/farmers')->assertInertia(fn($page) => $page->where('permissions.force_logout', false));
});
