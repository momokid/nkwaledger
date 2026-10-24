<?php

use App\Models\FarmTypeCategory;
use App\Models\HealthCategory;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->crop = FarmTypeCategory::factory()->create(['name' => 'Crop']);
    $this->livestock = FarmTypeCategory::factory()->create(['name' => 'Livestock']);
});

it('turns a guest away', function () {
    $this->get('/admin/health-categories')->assertRedirect('/login');
});

it('turns away an admin without health-categories.manage', function () {
    \App\Models\UserPermissionDenial::create([
        'user_id' => $this->admin->id,
        'permission_id' => \Spatie\Permission\Models\Permission::where('name', 'health-categories.manage')->value('id'),
        'denied_by' => User::factory()->create()->id,
    ]);

    $this->actingAs($this->admin)->get('/admin/health-categories')->assertForbidden();
});

it('turns away a farmer', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $this->actingAs($farmer)->get('/admin/health-categories')->assertForbidden();
});

it('lists categories per farm category in order, with uuids and no numeric ids', function () {
    $b = HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id, 'name' => 'B', 'sort_order' => 2]);
    $a = HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id, 'name' => 'A', 'sort_order' => 1, 'is_active' => false]);

    $this->actingAs($this->admin)
        ->get('/admin/health-categories')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Admin/HealthCategories/Index')
            ->where('groups.0.name', 'Crop')
            ->where('groups.0.categories.0.uuid', $a->uuid)
            ->where('groups.0.categories.0.is_active', false)
            ->where('groups.0.categories.1.uuid', $b->uuid)
            ->missing('groups.0.id')
            ->missing('groups.0.categories.0.id')
            ->has('groups.1.categories', 0));
});

it('adds a category at the end of its farm category', function () {
    HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id, 'sort_order' => 4]);

    $this->actingAs($this->admin)
        ->post('/admin/health-categories', ['farm_category' => 'Crop', 'name' => 'Frost damage'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('health_categories', [
        'farm_type_category_id' => $this->crop->id,
        'name' => 'Frost damage',
        'sort_order' => 5,
        'is_active' => true,
    ]);
});

it('refuses a name already used in the same farm category but allows it in another', function () {
    HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id, 'name' => 'Pests']);

    $this->actingAs($this->admin)
        ->post('/admin/health-categories', ['farm_category' => 'Crop', 'name' => 'Pests'])
        ->assertSessionHasErrors(['name' => 'This name is already used in this farm category.']);

    $this->actingAs($this->admin)
        ->post('/admin/health-categories', ['farm_category' => 'Livestock', 'name' => 'Pests'])
        ->assertSessionHasNoErrors();
});

it('refuses a missing or too long name', function () {
    $this->actingAs($this->admin)
        ->post('/admin/health-categories', ['farm_category' => 'Crop', 'name' => ''])
        ->assertSessionHasErrors(['name' => 'Enter a category name.']);

    $this->actingAs($this->admin)
        ->post('/admin/health-categories', ['farm_category' => 'Crop', 'name' => str_repeat('a', 101)])
        ->assertSessionHasErrors(['name' => 'The name can be at most 100 characters.']);
});

it('renames a category, and keeping its own name is not a clash', function () {
    $category = HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id, 'name' => 'Old']);

    $this->actingAs($this->admin)->patch("/admin/health-categories/{$category->uuid}", ['name' => 'Old'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->patch("/admin/health-categories/{$category->uuid}", ['name' => 'New'])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('New');
});

it('refuses a rename to a name used by a sibling', function () {
    HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id, 'name' => 'Taken']);
    $category = HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id, 'name' => 'Mine']);

    $this->actingAs($this->admin)->patch("/admin/health-categories/{$category->uuid}", ['name' => 'Taken'])
        ->assertSessionHasErrors('name');
});

it('switches a category off and on, even when reports use it', function () {
    $category = HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id]);

    $this->actingAs($this->admin)->patch("/admin/health-categories/{$category->uuid}", ['is_active' => false]);
    expect($category->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->patch("/admin/health-categories/{$category->uuid}", ['is_active' => true]);
    expect($category->fresh()->is_active)->toBeTrue();
});

it('moves a category up and down, even when sort orders tie', function () {
    $a = HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id, 'name' => 'A', 'sort_order' => 0]);
    $b = HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id, 'name' => 'B', 'sort_order' => 0]);
    $c = HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id, 'name' => 'C', 'sort_order' => 0]);
    $other = HealthCategory::factory()->create(['farm_type_category_id' => $this->livestock->id, 'sort_order' => 0]);

    $order = fn() => HealthCategory::where('farm_type_category_id', $this->crop->id)
        ->orderBy('sort_order')->pluck('name')->all();

    $this->actingAs($this->admin)->patch("/admin/health-categories/{$c->uuid}/move", ['direction' => 'up']);
    expect($order())->toBe(['A', 'C', 'B']);

    $this->actingAs($this->admin)->patch("/admin/health-categories/{$a->uuid}/move", ['direction' => 'down']);
    expect($order())->toBe(['C', 'A', 'B']);

    // at the edge nothing happens
    $this->actingAs($this->admin)->patch("/admin/health-categories/{$b->uuid}/move", ['direction' => 'down']);
    expect($order())->toBe(['C', 'A', 'B'])
        ->and($other->fresh()->sort_order)->toBe(0);
});

it('refuses a bad move direction', function () {
    $category = HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id]);

    $this->actingAs($this->admin)->patch("/admin/health-categories/{$category->uuid}/move", ['direction' => 'sideways'])
        ->assertSessionHasErrors('direction');
});

it('has no delete route', function () {
    $category = HealthCategory::factory()->create(['farm_type_category_id' => $this->crop->id]);

    $this->actingAs($this->admin)->delete("/admin/health-categories/{$category->uuid}")->assertStatus(405);
});
