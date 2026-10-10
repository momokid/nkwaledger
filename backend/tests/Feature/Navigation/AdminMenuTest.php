<?php

use App\Models\User;
use App\Models\UserPermissionDenial;
use App\Services\NavigationAccessService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->service = app(NavigationAccessService::class);
});

function menuLeaves(array $menu): array
{
    $leaves = [];

    foreach ($menu as $entry) {
        foreach ($entry['children'] ?? [$entry] as $leaf) {
            $leaves[] = $leaf;
        }
    }

    return $leaves;
}

test('the menu the server builds for an admin includes Market Center', function () {
    $routes = collect(menuLeaves($this->service->adminMenu($this->admin)))->pluck('routeName');

    expect($routes)->toContain('market-center.index')
        ->and($routes)->toContain('admin.dashboard')
        ->and($routes)->toContain('admin.marketplace.dashboard');
});

test('Market Center sits inside the Marketplace group', function () {
    $marketplace = collect($this->service->adminMenu($this->admin))->firstWhere('label', 'Marketplace');

    expect(collect($marketplace['children'])->pluck('routeName'))->toContain('market-center.index');
});

test('coming-soon entries are kept, since they have no route to gate on', function () {
    $marketplace = collect($this->service->adminMenu($this->admin))->firstWhere('label', 'Marketplace');

    $soon = collect($marketplace['children'])
        ->filter(fn($leaf) => ($leaf['ready'] ?? true) === false)
        ->pluck('label')
        ->values()
        ->all();

    expect($soon)->toBe(['Product Analysis', 'Finance']);
});

test('a leaf the admin has been denied is left out, and an emptied group goes with it', function () {
    UserPermissionDenial::create([
        'user_id' => $this->admin->id,
        'permission_id' => Permission::where('name', 'farm-types.view')->value('id'),
        'denied_by' => User::factory()->create()->id,
    ]);

    $menu = $this->service->adminMenu($this->admin);
    $routes = collect(menuLeaves($menu))->pluck('routeName');

    expect($routes)->not->toContain('admin.farm-types.index')
        ->and($routes)->toContain('admin.farmers.index');
});

test('a non-admin gets no admin menu at all, whatever permissions they hold', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');
    $agent->givePermissionTo(Permission::pluck('name')->all());

    expect($this->service->adminMenu($agent))->toBe([])
        ->and($this->service->adminMenu(null))->toBe([]);
});

test('the admin layout gets its menu from the server prop', function () {
    $this->actingAs($this->admin)->get('/admin/dashboard')
        ->assertInertia(fn($page) => $page->has('auth.adminMenu'));
});

test('an agent page carries no admin menu', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    $this->actingAs($agent)->get('/agent/dashboard')
        ->assertInertia(fn($page) => $page->where('auth.adminMenu', []));
});

test('AdminLayout.tsx no longer carries a menu of its own', function () {
    $source = file_get_contents(resource_path('js/Layouts/AdminLayout.tsx'));

    expect($source)->not->toContain('routeName: "admin.')
        ->and($source)->not->toContain('const navItems')
        ->and($source)->toContain('adminMenu');
});
