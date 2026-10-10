<?php

require_once __DIR__ . '/../../Support/OrphanedFarmer.php';

use App\Models\AuditLog;
use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPermissionDenial;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

const LOCK_NOTICE = ' has been locked. They cannot sign in until an admin unlocks the account.';
const UNLOCK_NOTICE = ' has been unlocked and can sign in again.';

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    config(['session.driver' => 'database']);
    app('session')->forgetDrivers();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->farmerUser = User::factory()->create(['phone' => '0244000801', 'surname' => 'Mensah', 'first_name' => 'Kofi']);
    $this->farmerUser->assignRole('farmer');
    $this->farmer = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id, 'assigned_agent_id' => $this->agent->id]);
});

function lockSessionFor(User $user): string
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

function lockPhone(array $cookies)
{
    app('auth')->forgetGuards();
    app('session.store')->flush();

    return test()->withCredentials()->withCookies($cookies);
}

function lockFarmer(User $by, FarmerProfile|string $farmer)
{
    app('auth')->forgetGuards();
    $uuid = $farmer instanceof FarmerProfile ? $farmer->uuid : $farmer;

    return test()->actingAs($by)->postJson("/admin/farmers/{$uuid}/lock");
}

function unlockFarmer(User $by, FarmerProfile|string $farmer)
{
    app('auth')->forgetGuards();
    $uuid = $farmer instanceof FarmerProfile ? $farmer->uuid : $farmer;

    return test()->actingAs($by)->postJson("/admin/farmers/{$uuid}/unlock");
}

function denyLockPermission(User $user, string $permission): void
{
    UserPermissionDenial::create([
        'user_id' => $user->id,
        'permission_id' => Permission::where('name', $permission)->value('id'),
        'denied_by' => $user->id,
        'reason' => 'test',
    ]);
}

function lockedByNow(User $user): void
{
    $user->forceFill(['locked_at' => now(), 'locked_by' => null])->save();
}

test('only an admin holds the lock and unlock permissions by default, as separate permissions', function () {
    $service = app(\App\Services\AccessControlService::class);

    foreach (['farmers.lock', 'farmers.unlock'] as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue()
            ->and($service->can($this->admin, $permission))->toBeTrue()
            ->and($service->can($this->agent, $permission))->toBeFalse()
            ->and($service->can($this->farmerUser, $permission))->toBeFalse();
    }
});

test('locking stamps the account with who and when, and ends every session and remember-me token', function () {
    $name = config('session.cookie');
    $session = lockSessionFor($this->farmerUser);
    $this->farmerUser->forceFill(['remember_token' => Str::random(60)])->save();
    $recaller = "{$this->farmerUser->id}|{$this->farmerUser->fresh()->remember_token}|{$this->farmerUser->password}";
    $recallerName = Auth::guard('web')->getRecallerName();
    $before = $this->farmerUser->fresh()->remember_token;

    lockPhone([$name => $session])->getJson('/auth/check')->assertOk();

    $response = lockFarmer($this->admin, $this->farmer)->assertOk();

    $fresh = $this->farmerUser->fresh();
    expect($response->json('status'))->toBe('locked')
        ->and($fresh->locked_at)->not->toBeNull()
        ->and($fresh->locked_by)->toBe($this->admin->id)
        ->and(DB::table('sessions')->where('user_id', $this->farmerUser->id)->count())->toBe(0)
        ->and($fresh->remember_token)->not->toBe($before);
    lockPhone([$name => $session])->getJson('/auth/check')->assertStatus(302);
    lockPhone([$recallerName => $recaller])->getJson('/auth/check')->assertStatus(302);
});

test('unlocking clears the lock and nothing else', function () {
    lockFarmer($this->admin, $this->farmer)->assertOk();
    $token = $this->farmerUser->fresh()->remember_token;

    $response = unlockFarmer($this->admin, $this->farmer)->assertOk();

    $fresh = $this->farmerUser->fresh();
    expect($response->json('status'))->toBe('unlocked')
        ->and($fresh->locked_at)->toBeNull()
        ->and($fresh->locked_by)->toBeNull()
        ->and($fresh->remember_token)->toBe($token)
        ->and($fresh->is_active)->toBeTrue();
});

test('the lock stays in force even if the admin who set it is removed', function () {
    $other = User::factory()->create();
    $other->assignRole('admin');
    lockFarmer($other, $this->farmer)->assertOk();

    DB::table('users')->where('id', $other->id)->delete();

    expect($this->farmerUser->fresh()->locked_at)->not->toBeNull()
        ->and($this->farmerUser->fresh()->locked_by)->toBeNull();
});

test('an admin who is also a farmer cannot lock or unlock themselves', function () {
    $both = User::factory()->create();
    $both->assignRole('admin');
    $both->assignRole('farmer');
    $own = FarmerProfile::factory()->create(['user_id' => $both->id]);

    lockFarmer($both, $own)->assertForbidden();
    lockedByNow($both);
    unlockFarmer($both, $own)->assertForbidden();

    expect($both->fresh()->locked_at)->not->toBeNull()
        ->and(AuditLog::whereIn('action', ['farmer.locked', 'farmer.unlocked'])->count())->toBe(0);
});

test('without the matching permission each action is refused and nothing changes', function () {
    denyLockPermission($this->admin, 'farmers.lock');

    lockFarmer($this->admin, $this->farmer)->assertForbidden();
    expect($this->farmerUser->fresh()->locked_at)->toBeNull();

    $other = User::factory()->create();
    $other->assignRole('admin');
    lockFarmer($other, $this->farmer)->assertOk();
    denyLockPermission($other, 'farmers.unlock');

    unlockFarmer($other, $this->farmer)->assertForbidden();
    expect($this->farmerUser->fresh()->locked_at)->not->toBeNull();
});

test('the force-logout permission alone does not allow locking, nor does lock allow unlock', function () {
    $forceOnly = User::factory()->create();
    $forceOnly->assignRole('admin');
    denyLockPermission($forceOnly, 'farmers.lock');
    denyLockPermission($forceOnly, 'farmers.unlock');

    expect(app(\App\Services\AccessControlService::class)->can($forceOnly, 'farmers.force-logout'))->toBeTrue();

    lockFarmer($forceOnly, $this->farmer)->assertForbidden();
    unlockFarmer($forceOnly, $this->farmer)->assertForbidden();

    $lockOnly = User::factory()->create();
    $lockOnly->assignRole('admin');
    denyLockPermission($lockOnly, 'farmers.unlock');
    lockFarmer($lockOnly, $this->farmer)->assertOk();
    unlockFarmer($lockOnly, $this->farmer)->assertForbidden();
});

test('an agent, even one handed both permissions, is refused on both routes', function () {
    $this->agent->givePermissionTo('farmers.lock', 'farmers.unlock');

    lockFarmer($this->agent, $this->farmer)->assertForbidden();
    expect($this->farmerUser->fresh()->locked_at)->toBeNull();

    lockedByNow($this->farmerUser);
    unlockFarmer($this->agent, $this->farmer)->assertForbidden();
    expect($this->farmerUser->fresh()->locked_at)->not->toBeNull();
});

test('an account that is not a farmer is refused even if it carries a farmer profile', function () {
    $stray = FarmerProfile::factory()->create(['user_id' => $this->agent->id]);

    lockFarmer($this->admin, $stray)->assertForbidden();

    expect($this->agent->fresh()->locked_at)->toBeNull();
});

test('staff and admin accounts cannot be reached on the farmer routes', function () {
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');

    lockFarmer($this->admin, $otherAdmin->uuid)->assertNotFound();
    lockFarmer($this->admin, $this->agent->uuid)->assertNotFound();
    unlockFarmer($this->admin, $otherAdmin->uuid)->assertNotFound();
    unlockFarmer($this->admin, $this->agent->uuid)->assertNotFound();

    expect(User::whereNotNull('locked_at')->count())->toBe(0);
});

test('a farmer with no linked login is refused with the server\'s own message', function () {
    $orphan = orphanFarmerProfile(FarmerProfile::factory()->create());

    expect(lockFarmer($this->admin, $orphan)->assertStatus(409)->json('message'))->toBe('Conflict');
    expect(unlockFarmer($this->admin, $orphan)->assertStatus(409)->json('message'))->toBe('Conflict');
});

test('locking an account that is locked, or unlocking one that is not, is refused and not repeated', function () {
    expect(unlockFarmer($this->admin, $this->farmer)->assertStatus(409)->json('message'))->toBe('Conflict');

    lockFarmer($this->admin, $this->farmer)->assertOk();
    expect(lockFarmer($this->admin, $this->farmer)->assertStatus(409)->json('message'))->toBe('Conflict');

    expect(AuditLog::where('action', 'farmer.locked')->count())->toBe(1)
        ->and(Notification::where('user_id', $this->agent->id)->where('kind', 'farmer.locked')->count())->toBe(1);
});

test('each lock and each unlock is audit logged with who, which farmer and when, and nothing sensitive', function () {
    lockFarmer($this->admin, $this->farmer)->assertOk();
    unlockFarmer($this->admin, $this->farmer)->assertOk();

    foreach (['farmer.locked', 'farmer.unlocked'] as $action) {
        $entry = AuditLog::where('action', $action)->sole();

        expect($entry->user_id)->toBe($this->admin->id)
            ->and($entry->auditable_id)->toBe($this->farmer->id)
            ->and($entry->auditable_type)->toBe(FarmerProfile::class)
            ->and($entry->created_at)->not->toBeNull()
            ->and($entry->old_values)->toBeNull()
            ->and($entry->new_values)->toBeNull();
    }
});

test('both actions are limited to ten a minute per admin', function () {
    for ($i = 0; $i < 5; $i++) {
        lockFarmer($this->admin, $this->farmer)->assertOk();
        unlockFarmer($this->admin, $this->farmer)->assertOk();
    }

    lockFarmer($this->admin, $this->farmer)->assertStatus(429);
    unlockFarmer($this->admin, $this->farmer)->assertStatus(429);

    $second = User::factory()->create();
    $second->assignRole('admin');
    lockFarmer($second, $this->farmer)->assertOk();
});

test('the farmer\'s own agent and the other admins are told, and the admin who did it is not', function () {
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');
    $strangerAgent = User::factory()->create();
    $strangerAgent->assignRole('agent');
    $name = 'Mensah Kofi';

    lockFarmer($this->admin, $this->farmer)->assertOk();

    $received = fn(User $user, string $kind) => Notification::where('user_id', $user->id)->where('kind', $kind)->pluck('message')->all();

    expect($received($this->agent, 'farmer.locked'))->toBe([$name . LOCK_NOTICE])
        ->and($received($otherAdmin, 'farmer.locked'))->toBe([$name . LOCK_NOTICE])
        ->and($received($this->admin, 'farmer.locked'))->toBe([])
        ->and($received($strangerAgent, 'farmer.locked'))->toBe([])
        ->and($received($this->farmerUser, 'farmer.locked'))->toBe([]);

    unlockFarmer($this->admin, $this->farmer)->assertOk();

    expect($received($this->agent, 'farmer.unlocked'))->toBe([$name . UNLOCK_NOTICE])
        ->and($received($otherAdmin, 'farmer.unlocked'))->toBe([$name . UNLOCK_NOTICE])
        ->and($received($this->admin, 'farmer.unlocked'))->toBe([])
        ->and($received($this->farmerUser, 'farmer.unlocked'))->toBe([]);
});

test('a farmer who holds no agent still notifies the other admins, and an agent who is also the actor is not told twice', function () {
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');
    $this->farmer->update(['assigned_agent_id' => null]);

    lockFarmer($this->admin, $this->farmer)->assertOk();

    expect(Notification::where('kind', 'farmer.locked')->pluck('user_id')->all())->toBe([$otherAdmin->id]);
});

test('the answer carries no numeric id', function () {
    expect(lockFarmer($this->admin, $this->farmer)->assertOk()->getContent())->not->toContain('"id"');
});

test('the lists tell an admin and an agent who is locked, and only the admin gets the buttons', function () {
    lockedByNow($this->farmerUser);

    app('auth')->forgetGuards();
    $this->actingAs($this->admin)->get('/admin/farmers')->assertOk()->assertInertia(fn($page) => $page
        ->where('permissions.lock', true)
        ->where('permissions.unlock', true)
        ->where('farmers.data', fn($rows) => collect($rows)->firstWhere('id', $this->farmer->uuid)['locked'] === true));

    app('auth')->forgetGuards();
    $this->actingAs($this->agent)->get('/agent/farmers')->assertOk()->assertInertia(fn($page) => $page
        ->where('permissions.lock', false)
        ->where('permissions.unlock', false)
        ->where('farmers.data', fn($rows) => collect($rows)->firstWhere('id', $this->farmer->uuid)['locked'] === true));

    denyLockPermission($this->admin, 'farmers.lock');
    app('auth')->forgetGuards();
    $this->actingAs($this->admin)->get('/admin/farmers')->assertInertia(fn($page) => $page
        ->where('permissions.lock', false)
        ->where('permissions.unlock', true));
});
