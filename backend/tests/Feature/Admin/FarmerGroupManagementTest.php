<?php

use App\Models\Community;
use App\Models\District;
use App\Models\FarmerGroup;
use App\Models\FarmerGroupType;
use App\Models\Region;
use App\Models\User;
use App\Models\UserPermissionDenial;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "farmer-groups.{$action}", 'guard_name' => 'web']);
    }
});

test('a guest is redirected to login when visiting farmer groups', function () {
    $this->get('/admin/farmer-groups')->assertRedirect('/login');
});

test('a user without farmer-groups.view cannot view the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/farmer-groups')->assertForbidden();
});

test('a user with farmer-groups.view granted directly can view the list', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->get('/admin/farmer-groups')->assertOk();
});

test('an admin who has been explicitly denied farmer-groups.view cannot view the list', function () {
    $denier = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('farmer-groups.view');

    UserPermissionDenial::create([
        'user_id' => $admin->id,
        'permission_id' => Permission::where('name', 'farmer-groups.view')->value('id'),
        'denied_by' => $denier->id,
    ]);

    $this->actingAs($admin)->get('/admin/farmer-groups')->assertForbidden();
});

test('a user without farmer-groups.create cannot create a farmer group', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->post('/admin/farmer-groups', [
        'name' => 'Kumbungu Cooperative',
    ])->assertForbidden();
});

test('a user with farmer-groups.create can create a farmer group with only a name', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/farmer-groups', [
        'name' => 'Kumbungu Cooperative',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('farmer_groups', [
        'name' => 'Kumbungu Cooperative',
        'created_by' => $user->id,
    ]);
});

test('created_by is set automatically from the authenticated user, not the request', function () {
    $user = User::factory()->create();
    $impersonated = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/farmer-groups', [
        'name' => 'Savelugu VSLA',
        'created_by' => $impersonated->id,
    ]);

    $this->assertDatabaseHas('farmer_groups', [
        'name' => 'Savelugu VSLA',
        'created_by' => $user->id,
    ]);
});

test('a user with farmer-groups.create can create a farmer group with all fields', function () {
    $type = FarmerGroupType::create(['name' => 'Cooperative']);
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Kalpohin', 'district_id' => $district->id]);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/farmer-groups', [
        'name' => 'Kalpohin Cooperative',
        'group_type_id' => $type->id,
        'region_id' => $region->id,
        'district_id' => $district->id,
        'community_id' => $community->id,
        'description' => 'A cocoa farming cooperative.',
        'is_shared_liability' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('farmer_groups', [
        'name' => 'Kalpohin Cooperative',
        'group_type_id' => $type->id,
        'region_id' => $region->id,
        'district_id' => $district->id,
        'community_id' => $community->id,
        'is_shared_liability' => true,
    ]);
});

test('creating a farmer group with a non-existent group_type_id fails validation', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/farmer-groups', [
        'name' => 'Bad Group',
        'group_type_id' => 999,
    ])->assertSessionHasErrors('group_type_id');
});

test('a user without farmer-groups.update cannot update a farmer group', function () {
    $creator = User::factory()->create();
    $group = FarmerGroup::factory()->create(['created_by' => $creator->id]);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->put("/admin/farmer-groups/{$group->id}", [
        'name' => 'Updated Name',
        'is_active' => false,
    ])->assertForbidden();
});

test('a user with farmer-groups.update can update a farmer group, including toggling is_active', function () {
    $creator = User::factory()->create();
    $group = FarmerGroup::factory()->create(['created_by' => $creator->id, 'is_active' => true]);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.update']);

    $this->actingAs($user)->put("/admin/farmer-groups/{$group->id}", [
        'name' => 'Updated Name',
        'is_active' => false,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('farmer_groups', [
        'id' => $group->id,
        'name' => 'Updated Name',
        'is_active' => false,
    ]);
});

test('a user without farmer-groups.delete cannot delete a farmer group', function () {
    $creator = User::factory()->create();
    $group = FarmerGroup::factory()->create(['created_by' => $creator->id]);
    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $this->actingAs($user)->delete("/admin/farmer-groups/{$group->id}")->assertForbidden();
});

test('a user with farmer-groups.delete can soft delete a farmer group', function () {
    $creator = User::factory()->create();
    $group = FarmerGroup::factory()->create(['created_by' => $creator->id]);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.delete']);

    $this->actingAs($user)->delete("/admin/farmer-groups/{$group->id}")
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertSoftDeleted('farmer_groups', ['id' => $group->id]);
});

test('a soft-deleted farmer group is excluded from the default index', function () {
    $creator = User::factory()->create();
    $active = FarmerGroup::factory()->create(['created_by' => $creator->id, 'name' => 'Active Group']);
    $deleted = FarmerGroup::factory()->create(['created_by' => $creator->id, 'name' => 'Deleted Group']);
    $deleted->delete();

    $user = User::factory()->create();
    $user->givePermissionTo('farmer-groups.view');

    $response = $this->actingAs($user)->get('/admin/farmer-groups');

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->has('farmerGroups.data', 1)
            ->where('farmerGroups.data.0.name', 'Active Group')
    );
});

test('creating a farmer group with a duplicate name fails validation', function () {
    FarmerGroup::factory()->create(['name' => 'Kumbungu Cooperative']);
    $user = User::factory()->create();
    $user->givePermissionTo(['farmer-groups.view', 'farmer-groups.create']);

    $this->actingAs($user)->post('/admin/farmer-groups', [
        'name' => 'Kumbungu Cooperative',
    ])->assertSessionHasErrors('name');
});
