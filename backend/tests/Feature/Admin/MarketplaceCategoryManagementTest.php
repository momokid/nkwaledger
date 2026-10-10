<?php

use App\Models\MarketplaceCategory;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

test('a guest cannot view marketplace categories', function () {
    $this->get('/admin/marketplace/categories')->assertRedirect('/login');
});

test('a user without marketplace-categories.view cannot view the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/marketplace/categories')->assertForbidden();
});

test('an admin can create a category, and it gets a unique slug and trailing sort order', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    MarketplaceCategory::factory()->create(['name' => 'Existing', 'sort_order' => 5]);

    $this->actingAs($admin)->post('/admin/marketplace/categories', [
        'name' => 'Farm Inputs',
        'display_count' => 6,
    ])->assertSessionHasNoErrors();

    $category = MarketplaceCategory::where('name', 'Farm Inputs')->first();

    expect($category)->not->toBeNull()
        ->and($category->slug)->toBe('farm-inputs')
        ->and($category->display_count)->toBe(6)
        ->and($category->sort_order)->toBe(6);
});

test('creating two categories with the same name gets two distinct slugs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->post('/admin/marketplace/categories', ['name' => 'Kiosk', 'display_count' => 8]);
    $this->actingAs($admin)->post('/admin/marketplace/categories', ['name' => 'Kiosk', 'display_count' => 8]);

    expect(MarketplaceCategory::where('name', 'Kiosk')->pluck('slug')->all())
        ->toEqual(['kiosk', 'kiosk-2']);
});

test('an admin can update a category\'s display_count and active state', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $category = MarketplaceCategory::factory()->create(['display_count' => 8, 'is_active' => true]);

    $this->actingAs($admin)->put("/admin/marketplace/categories/{$category->slug}", [
        'name' => $category->name,
        'display_count' => 20,
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    expect($category->fresh()->display_count)->toBe(20)
        ->and($category->fresh()->is_active)->toBeFalse();
});

test('an admin can reorder categories in one call', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $first = MarketplaceCategory::factory()->create(['sort_order' => 0]);
    $second = MarketplaceCategory::factory()->create(['sort_order' => 1]);

    $this->actingAs($admin)->post('/admin/marketplace/categories/reorder', [
        'ids' => [$second->id, $first->id],
    ])->assertSessionHasNoErrors();

    expect($second->fresh()->sort_order)->toBe(0)
        ->and($first->fresh()->sort_order)->toBe(1);
});

test('an admin can delete a category', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $category = MarketplaceCategory::factory()->create();

    $this->actingAs($admin)->delete("/admin/marketplace/categories/{$category->slug}")->assertSessionHasNoErrors();

    expect(MarketplaceCategory::find($category->id))->toBeNull();
});
