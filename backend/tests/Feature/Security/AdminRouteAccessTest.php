<?php

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Every route in the admin.* namespace is found programmatically, so a route added later is
// covered the day it is added. role:admin is the boundary: no permission a non-admin holds,
// however many, may ever open one of these.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

function adminRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn(LaravelRoute $route) => str_starts_with((string) $route->getName(), 'admin.'))
        ->values()
        ->all();
}

// no record needs to exist: the gate has to answer before route-model binding looks for one,
// so parameters only need a value of the right shape (a uuid where a route pattern demands one)
function adminUrl(LaravelRoute $route): string
{
    $patterns = array_merge(Route::getPatterns(), $route->wheres);

    $uri = preg_replace('/\{[^}?]+\?\}/', '', $route->uri());

    $uri = preg_replace_callback('/\{([^}]+)\}/', function (array $match) use ($patterns) {
        $pattern = $patterns[$match[1]] ?? '';

        return str_contains($pattern, '{36}') ? '00000000-0000-4000-8000-000000000000' : '1';
    }, $uri);

    return '/' . trim($uri, '/');
}

function adminMethod(LaravelRoute $route): string
{
    return collect($route->methods())->first(fn(string $method) => ! in_array($method, ['HEAD', 'OPTIONS'], true));
}

function everyPermissionName(): array
{
    $names = [];

    foreach (config('permissions.modules') as $module => $config) {
        foreach (array_keys($config['actions']) as $action) {
            $names[] = "{$module}.{$action}";
        }
    }

    return array_merge($names, array_keys(config('permissions.standalone')));
}

// every admin.* route this user can get past the gate on, as "METHOD url -> status"
function adminRoutesOpenTo(User $user, string $label): array
{
    $open = [];

    foreach (adminRoutes() as $route) {
        $status = test()->actingAs($user)->call(adminMethod($route), adminUrl($route))->getStatusCode();

        // the OTP "resend" routes are rate limited ahead of the role gate, and this loop is
        // deliberately many requests from one client: clear the limiter and ask again, so what
        // is asserted is the gate's answer rather than the throttle's
        if ($status === 429) {
            Cache::flush();
            $status = test()->actingAs($user)->call(adminMethod($route), adminUrl($route))->getStatusCode();
        }

        if ($status !== 403) {
            $open[] = "{$label}: {$route->getName()} " . adminMethod($route) . ' ' . adminUrl($route) . " -> {$status}";
        }
    }

    return $open;
}

test('the enumeration is not vacuous', function () {
    expect(count(adminRoutes()))->toBeGreaterThan(100);
    expect(count(everyPermissionName()))->toBeGreaterThan(50);
});

test('every URL under /admin is an admin.* route that carries role:admin', function () {
    $loose = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'admin/') && $route->uri() !== 'admin') {
            continue;
        }

        $named = str_starts_with((string) $route->getName(), 'admin.');
        $gated = in_array('role:admin', $route->gatherMiddleware(), true);

        if (! $named || ! $gated) {
            $loose[] = $route->uri() . ' (' . ($route->getName() ?? 'unnamed') . ')';
        }
    }

    expect($loose)->toBe([]);
});

test('every admin.* route carries role:admin', function () {
    $ungated = collect(adminRoutes())
        ->reject(fn(LaravelRoute $route) => in_array('role:admin', $route->gatherMiddleware(), true))
        ->map(fn(LaravelRoute $route) => $route->getName())
        ->values()
        ->all();

    expect($ungated)->toBe([]);
});

test('a guest gets no admin.* route', function () {
    $open = [];

    foreach (adminRoutes() as $route) {
        $status = $this->call(adminMethod($route), adminUrl($route))->getStatusCode();

        if ($status !== 302) {
            $open[] = "{$route->getName()} -> {$status}";
        }
    }

    expect($open)->toBe([]);
});

test('every non-admin role gets 403 on every admin.* route', function () {
    $roles = Role::where('name', '!=', 'admin')->pluck('name')->all();
    $roles[] = Role::create(['name' => 'brand-new-role', 'guard_name' => 'web'])->name;

    expect($roles)->toContain('farmer', 'agent', 'vet', 'adviser', 'supplier', 'brand-new-role');

    $open = [];

    foreach ($roles as $name) {
        $user = User::factory()->create();
        $user->assignRole($name);

        $open = array_merge($open, adminRoutesOpenTo($user, $name));
    }

    // and someone with no role at all
    $open = array_merge($open, adminRoutesOpenTo(User::factory()->create(), 'no role'));

    expect($open)->toBe([]);
});

// proves the role gate works on its own: holding every permission in config buys nothing
test('a non-admin holding every permission in config still gets 403 on every admin.* route', function () {
    $everything = everyPermissionName();

    foreach ($everything as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue("{$name} is not seeded");
    }

    $open = [];

    foreach (['agent', 'vet', 'farmer', 'brand-new-role'] as $name) {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($name);
        $user->givePermissionTo($everything);

        expect($user->fresh()->hasRole('admin'))->toBeFalse();
        expect($user->getAllPermissions()->pluck('name')->diff($everything)->all())->toBe([]);

        $open = array_merge($open, adminRoutesOpenTo($user, "{$name}+everything"));
    }

    expect($open)->toBe([]);
});

test('the same harness does let an admin through, so a 403 here means something', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $status = $this->actingAs($admin)->get(route('admin.dashboard'))->getStatusCode();

    expect($status)->not->toBe(403);
});
