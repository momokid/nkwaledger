<?php

use App\Models\User;
use App\Models\UserPermissionDenial;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Permission;

test('a permission can be denied for a user', function () {
    $target = User::factory()->create();
    $admin = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'farm-types.view', 'guard_name' => 'web']);

    $denial = UserPermissionDenial::create([
        'user_id' => $target->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]);

    expect($denial->user_id)->toBe($target->id);
    expect($denial->permission_id)->toBe($permission->id);
    expect($denial->denied_by)->toBe($admin->id);
});

test('a denial belongs to the user it targets', function () {
    $target = User::factory()->create();
    $admin = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'farm-types.view', 'guard_name' => 'web']);

    $denial = UserPermissionDenial::create([
        'user_id' => $target->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]);

    expect($denial->user->id)->toBe($target->id);
});

test('a denial belongs to the admin who created it', function () {
    $target = User::factory()->create();
    $admin = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'farm-types.view', 'guard_name' => 'web']);

    $denial = UserPermissionDenial::create([
        'user_id' => $target->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]);

    expect($denial->deniedBy->id)->toBe($admin->id);
});

test('a denial belongs to a permission', function () {
    $target = User::factory()->create();
    $admin = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'farm-types.view', 'guard_name' => 'web']);

    $denial = UserPermissionDenial::create([
        'user_id' => $target->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]);

    expect($denial->permission->name)->toBe('farm-types.view');
});

test('the same permission cannot be denied twice for the same user', function () {
    $target = User::factory()->create();
    $admin = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'farm-types.view', 'guard_name' => 'web']);

    UserPermissionDenial::create([
        'user_id' => $target->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]);

    expect(fn() => UserPermissionDenial::create([
        'user_id' => $target->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]))->toThrow(QueryException::class);
});

test('a denial can be removed to restore default access', function () {
    $target = User::factory()->create();
    $admin = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'farm-types.view', 'guard_name' => 'web']);

    $denial = UserPermissionDenial::create([
        'user_id' => $target->id,
        'permission_id' => $permission->id,
        'denied_by' => $admin->id,
    ]);

    $denial->delete();

    expect(UserPermissionDenial::find($denial->id))->toBeNull();
});
