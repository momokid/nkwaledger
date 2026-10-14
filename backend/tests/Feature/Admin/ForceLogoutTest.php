<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserKnownDevice;
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

    $this->agent = User::factory()->create(['phone' => '0244000601']);
    $this->agent->assignRole('agent');
});

// a real session row for this user, the way a sign-in leaves one, and its id (the test client encrypts cookies itself)
function sessionFor(User $user): array
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

    return [$id, $id];
}

function recallerFor(User $user): string
{
    return "{$user->id}|{$user->remember_token}|{$user->password}";
}

// a request carrying only these cookies, as a phone that kept them would send it
function asPhone(array $cookies)
{
    // a phone's request starts from nothing: the test app would otherwise carry the last request's session over
    app('auth')->forgetGuards();
    app('session.store')->flush();

    return test()->withCredentials()->withCookies($cookies);
}

function forceLogout(User $admin, User $target)
{
    app('auth')->forgetGuards();

    return test()->actingAs($admin)->postJson("/admin/staff/{$target->uuid}/force-logout");
}

test('the old session stops working once the user is signed out everywhere', function () {
    [$id, $cookie] = sessionFor($this->agent);
    $name = config('session.cookie');

    asPhone([$name => $cookie])->getJson('/auth/check')->assertOk();

    forceLogout($this->admin, $this->agent)->assertOk();

    expect(DB::table('sessions')->where('user_id', $this->agent->id)->count())->toBe(0);
    asPhone([$name => $cookie])->getJson('/auth/check')->assertStatus(302);
});

test('every session the user has, on every device, goes at once', function () {
    foreach (range(1, 3) as $device) {
        sessionFor($this->agent);
    }
    [$adminSession] = sessionFor($this->admin);

    forceLogout($this->admin, $this->agent)->assertOk();

    expect(DB::table('sessions')->where('user_id', $this->agent->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', $adminSession)->exists())->toBeTrue();
});

test('the old remember-me token stops working too', function () {
    $this->agent->forceFill(['remember_token' => Str::random(60)])->save();
    $recaller = recallerFor($this->agent->fresh());
    $name = Auth::guard('web')->getRecallerName();

    asPhone([$name => $recaller])->getJson('/auth/check')->assertOk();

    forceLogout($this->admin, $this->agent)->assertOk();

    expect($this->agent->fresh()->remember_token)->not->toBe($this->agent->remember_token);
    asPhone([$name => $recaller])->getJson('/auth/check')->assertStatus(302);
});

test('the user can sign in again afterwards, and the phone is let back in', function () {
    UserKnownDevice::create(['user_id' => $this->agent->id, 'fingerprint' => hash('sha256', '127.0.0.1|Symfony'), 'last_seen_at' => now()]);
    forceLogout($this->admin, $this->agent)->assertOk();

    app('auth')->forgetGuards();
    $this->post('/login', ['identifier' => '0244000601', 'password' => 'Password@123'])->assertRedirect();

    expect($this->agent->fresh()->is_active)->toBeTrue();

    [, $cookie] = sessionFor($this->agent->fresh());
    asPhone([config('session.cookie') => $cookie])->getJson('/auth/check')->assertOk();
});

test('an old session cannot send saved records, and a new one can', function () {
    [, $old] = sessionFor($this->agent);
    forceLogout($this->admin, $this->agent)->assertOk();
    $name = config('session.cookie');

    asPhone([$name => $old])->postJson('/sync/submissions', ['records' => []])->assertUnauthorized();

    [, $fresh] = sessionFor($this->agent);
    asPhone([$name => $fresh])->postJson('/sync/submissions', ['records' => []])->assertStatus(422);
});

test('an admin cannot sign themselves out this way', function () {
    forceLogout($this->admin, $this->admin)->assertForbidden();

    expect($this->admin->fresh()->remember_token)->toBe($this->admin->remember_token);
});

test('a user without the permission is refused, and nothing is touched', function () {
    [, $cookie] = sessionFor($this->agent);
    $other = User::factory()->create();
    $other->assignRole('agent');

    forceLogout($other, $this->agent)->assertForbidden();

    expect(DB::table('sessions')->where('user_id', $this->agent->id)->count())->toBe(1);
});

test('an admin whose permission was taken away is refused', function () {
    sessionFor($this->agent);
    UserPermissionDenial::create([
        'user_id' => $this->admin->id,
        'permission_id' => Permission::where('name', 'staff.force-logout')->value('id'),
        'denied_by' => $this->admin->id,
        'reason' => 'test',
    ]);

    forceLogout($this->admin, $this->agent)->assertForbidden();

    expect(DB::table('sessions')->where('user_id', $this->agent->id)->count())->toBe(1);
});

test('a user the admin may not manage is refused: another admin or a farmer', function () {
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');
    sessionFor($otherAdmin);
    sessionFor($farmer);

    forceLogout($this->admin, $otherAdmin)->assertForbidden();
    forceLogout($this->admin, $farmer)->assertForbidden();

    expect(DB::table('sessions')->whereIn('user_id', [$otherAdmin->id, $farmer->id])->count())->toBe(2);
});

test('each use is audit logged with who, which user and when, and nothing sensitive', function () {
    sessionFor($this->agent);

    forceLogout($this->admin, $this->agent)->assertOk();

    $entry = AuditLog::where('action', 'staff.forced_logout')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($this->admin->id)
        ->and($entry->auditable_id)->toBe($this->agent->id)
        ->and($entry->auditable_type)->toBe(User::class)
        ->and($entry->created_at)->not->toBeNull()
        ->and($entry->old_values)->toBeNull()
        ->and($entry->new_values)->toBeNull();
});

test('a refused attempt leaves no forced-logout entry', function () {
    forceLogout($this->admin, $this->admin)->assertForbidden();

    expect(AuditLog::where('action', 'staff.forced_logout')->count())->toBe(0);
});

test('the action is limited per admin', function () {
    for ($i = 0; $i < 10; $i++) {
        forceLogout($this->admin, $this->agent)->assertOk();
    }

    forceLogout($this->admin, $this->agent)->assertStatus(429);

    $second = User::factory()->create();
    $second->assignRole('admin');
    forceLogout($second, $this->agent)->assertOk();
});

test('the answer carries no numeric id', function () {
    $body = forceLogout($this->admin, $this->agent)->assertOk()->getContent();

    expect($body)->not->toContain((string) $this->agent->id . ',')->and($body)->not->toContain('"id"');
});
