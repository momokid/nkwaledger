<?php

use App\Http\Middleware\EnsureMarketplaceEnabled;
use App\Models\User;
use App\Services\NavigationAccessService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;

const MARKETPLACE_ROUTE = '/market|kiosk|produce|listing|supplier|contact-request/i';

beforeEach(function () {
    config(['features.marketplace' => false]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

function marketplaceRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn($r) => preg_match(MARKETPLACE_ROUTE, $r->uri() . ' ' . $r->getName()))
        ->reject(fn($r) => $r->getName() === 'supplier.dashboard')
        ->all();
}

test('every marketplace route carries the switch middleware', function () {
    expect(marketplaceRoutes())->not->toBeEmpty();

    foreach (marketplaceRoutes() as $route) {
        expect($route->gatherMiddleware())->toContain('marketplace');
    }
});

test('with the switch off every marketplace route is 404 for every role', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    foreach (marketplaceRoutes() as $route) {
        $uri = preg_replace('/\{[^}]+\}/', 'x', $route->uri());
        $method = collect($route->methods())->first(fn($m) => $m !== 'HEAD');

        $this->actingAs($user)->call($method, '/' . $uri)->assertNotFound();
    }
})->with(['farmer', 'supplier', 'agent', 'vet', 'adviser', 'admin']);

test('with the switch off no marketplace item appears in the menus', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $nav = app(NavigationAccessService::class);

    $menu = json_encode($nav->adminMenu($admin));

    expect($menu)->not->toContain('arketplace')->not->toContain('Market Center')
        ->and($nav->allowedRouteNames($admin))->not->toContain('market-center.index')
        ->and(collect($nav->allowedRouteNames($admin))->filter(fn($n) => str_contains($n, 'marketplace')))->toBeEmpty();
});

test('the browser is told the switch is off', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/farmer/dashboard')
        ->assertInertia(fn($page) => $page->where('features.marketplace', false));
});

test('a supplier with the switch off sees only the coming soon page', function () {
    $supplier = User::factory()->create();
    $supplier->assignRole('supplier');

    $this->actingAs($supplier)->get('/supplier/dashboard')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Supplier/ComingSoon'));
});

test('a supplier with the switch on sees the normal dashboard', function () {
    config(['features.marketplace' => true]);
    $supplier = User::factory()->create();
    $supplier->assignRole('supplier');

    $this->actingAs($supplier)->get('/supplier/dashboard')
        ->assertInertia(fn($page) => $page->component('Supplier/Dashboard'));
});

test('with the switch off the marketplace schedule does not run, the rest still does', function () {
    $events = collect(Schedule::events());
    [$market, $other] = $events->partition(fn($e) => str_contains($e->command, 'marketplace:'));

    expect($market)->toHaveCount(10);

    config(['features.marketplace' => false]);
    expect($market->every(fn($e) => ! $e->filtersPass(app())))->toBeTrue()
        ->and($other->every(fn($e) => $e->filtersPass(app())))->toBeTrue();

    config(['features.marketplace' => true]);
    expect($market->every(fn($e) => $e->filtersPass(app())))->toBeTrue();
});
