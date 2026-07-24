<?php

use App\Models\FarmerGroup;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a farmer group can be created', function () {
    $admin = User::factory()->create();

    $group = FarmerGroup::create([
        'name' => 'Kumbungu Maize Cooperative',
        'group_type' => 'cooperative',
        'region' => 'Northern',
        'created_by' => $admin->id,
    ]);

    expect($group->name)->toBe('Kumbungu Maize Cooperative');
    expect($group->group_type)->toBe('cooperative');
    expect($group->is_shared_liability)->toBeFalse();
    expect($group->is_active)->toBeTrue();
});

test('group type must be a recognized value', function () {
    $admin = User::factory()->create();

    expect(fn() => FarmerGroup::create([
        'name' => 'Random Group',
        'group_type' => 'social_club',
        'created_by' => $admin->id,
    ]))->toThrow(InvalidArgumentException::class);
});

test('created_by is required', function () {
    expect(fn() => FarmerGroup::create([
        'name' => 'Orphan Group',
        'group_type' => 'vsla',
    ]))->toThrow(QueryException::class);
});

test('a farmer group belongs to the user who created it', function () {
    $admin = User::factory()->create();

    $group = FarmerGroup::create([
        'name' => 'Savelugu VSLA',
        'group_type' => 'vsla',
        'created_by' => $admin->id,
    ]);

    expect($group->creator->id)->toBe($admin->id);
});

test('a farmer group can be marked shared liability', function () {
    $admin = User::factory()->create();

    $group = FarmerGroup::create([
        'name' => 'Tamale Outgrowers',
        'group_type' => 'outgrower',
        'is_shared_liability' => true,
        'created_by' => $admin->id,
    ]);

    expect($group->is_shared_liability)->toBeTrue();
});

test('a farmer group can be deactivated without being deleted', function () {
    $admin = User::factory()->create();

    $group = FarmerGroup::create([
        'name' => 'Yendi Cooperative',
        'group_type' => 'cooperative',
        'created_by' => $admin->id,
    ]);

    $group->update(['is_active' => false]);

    expect($group->fresh()->is_active)->toBeFalse();
    expect(FarmerGroup::find($group->id))->not->toBeNull();
});

test('a farmer group can be soft deleted', function () {
    $admin = User::factory()->create();

    $group = FarmerGroup::create([
        'name' => 'Tolon VSLA',
        'group_type' => 'vsla',
        'created_by' => $admin->id,
    ]);

    $group->delete();

    expect(FarmerGroup::find($group->id))->toBeNull();
    expect(FarmerGroup::withTrashed()->find($group->id))->not->toBeNull();
});
