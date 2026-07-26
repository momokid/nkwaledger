<?php

use App\Models\Community;
use App\Models\District;
use App\Models\FarmerGroup;
use App\Models\FarmerGroupType;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a farmer group can be created', function () {
    $admin = User::factory()->create();
    $type = FarmerGroupType::create(['name' => 'Cooperative']);

    $group = FarmerGroup::create([
        'name' => 'Kumbungu Maize Cooperative',
        'group_type_id' => $type->id,
        'created_by' => $admin->id,
    ]);

    expect($group->name)->toBe('Kumbungu Maize Cooperative');
    expect($group->group_type_id)->toBe($type->id);
    expect($group->is_shared_liability)->toBeFalse();
    expect($group->is_active)->toBeTrue();
});

test('a farmer group can be created without a group type', function () {
    $admin = User::factory()->create();

    $group = FarmerGroup::create([
        'name' => 'Unclassified Group',
        'created_by' => $admin->id,
    ]);

    expect($group->group_type_id)->toBeNull();
    expect($group->groupType)->toBeNull();
});

test('created_by is required', function () {
    expect(fn() => FarmerGroup::create([
        'name' => 'Orphan Group',
    ]))->toThrow(QueryException::class);
});

test('a farmer group belongs to the user who created it', function () {
    $admin = User::factory()->create();

    $group = FarmerGroup::create([
        'name' => 'Savelugu VSLA',
        'created_by' => $admin->id,
    ]);

    expect($group->creator->id)->toBe($admin->id);
});

test('a farmer group resolves its group type, region, district, and community relationships', function () {
    $admin = User::factory()->create();
    $type = FarmerGroupType::create(['name' => 'VSLA']);
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Kalpohin', 'district_id' => $district->id]);

    $group = FarmerGroup::create([
        'name' => 'Kalpohin VSLA',
        'group_type_id' => $type->id,
        'region_id' => $region->id,
        'district_id' => $district->id,
        'community_id' => $community->id,
        'created_by' => $admin->id,
    ]);

    expect($group->groupType->name)->toBe('VSLA');
    expect($group->region->name)->toBe('Northern');
    expect($group->district->name)->toBe('Tamale');
    expect($group->community->name)->toBe('Kalpohin');
});

test('a farmer group can be marked shared liability', function () {
    $admin = User::factory()->create();

    $group = FarmerGroup::create([
        'name' => 'Tamale Outgrowers',
        'is_shared_liability' => true,
        'created_by' => $admin->id,
    ]);

    expect($group->is_shared_liability)->toBeTrue();
});

test('a farmer group can be deactivated without being deleted', function () {
    $admin = User::factory()->create();

    $group = FarmerGroup::create([
        'name' => 'Yendi Cooperative',
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
        'created_by' => $admin->id,
    ]);

    $group->delete();

    expect(FarmerGroup::find($group->id))->toBeNull();
    expect(FarmerGroup::withTrashed()->find($group->id))->not->toBeNull();
});

test('a farmer group loses its group type reference when the type is soft deleted', function () {
    $admin = User::factory()->create();
    $type = FarmerGroupType::create(['name' => 'Other']);

    $group = FarmerGroup::create([
        'name' => 'Misc Group',
        'group_type_id' => $type->id,
        'created_by' => $admin->id,
    ]);

    $type->delete();

    expect($group->fresh()->group_type_id)->toBeNull();
});

test('a farmer group loses its region, district, and community references when the region is soft deleted', function () {
    $admin = User::factory()->create();
    $region = Region::create(['name' => 'Bono']);
    $district = District::create(['name' => 'Sunyani', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Abesim', 'district_id' => $district->id]);

    $group = FarmerGroup::create([
        'name' => 'Abesim Cooperative',
        'region_id' => $region->id,
        'district_id' => $district->id,
        'community_id' => $community->id,
        'created_by' => $admin->id,
    ]);

    $region->delete();

    $fresh = $group->fresh();
    expect($fresh->region_id)->toBeNull();
    expect($fresh->district_id)->toBeNull();
    expect($fresh->community_id)->toBeNull();
});
