<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

class NavigationAccessService
{
    // route groups that carry a sidebar, so a new group joins by adding its prefix here
    private const PREFIXES = ['admin.', 'agent.', 'my-records.', 'my-reports.', 'my-farm.'];

    public function __construct(private AccessControlService $access) {}

    /**
     * @return array<int, string>
     */
    public function allowedRouteNames(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $names = [];

        foreach (RouteFacade::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! $this->isNavigable($name)) {
                continue;
            }

            // a sidebar can only link to a page the browser can open
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if ($this->passes($user, $route)) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The admin sidebar, already filtered to what this admin may open. Anyone who is not an
     * admin gets nothing, whatever permissions they hold: the role is the boundary.
     *
     * @return array<int, array<string, mixed>>
     */
    public function adminMenu(?User $user): array
    {
        if ($user === null || ! $user->hasRole('admin')) {
            return [];
        }

        $menu = [];

        foreach (config('admin_menu') as $entry) {
            if (isset($entry['feature']) && ! config('features.' . $entry['feature'])) {
                continue;
            }

            if (! isset($entry['children'])) {
                $leaf = $this->menuLeaf($user, $entry);

                if ($leaf !== null) {
                    $menu[] = $leaf;
                }

                continue;
            }

            $children = array_values(array_filter(
                array_map(fn(array $child) => $this->menuLeaf($user, $child), $entry['children'])
            ));

            // a group with nothing left to open is not worth showing
            if ($children !== []) {
                $menu[] = ['label' => $entry['label'], 'icon' => $entry['icon'] ?? null, 'children' => $children];
            }
        }

        return $menu;
    }

    private function menuLeaf(User $user, array $leaf): ?array
    {
        // a coming-soon placeholder has no route to be permission-gated on, so it always shows
        if (($leaf['ready'] ?? true) === false) {
            return ['label' => $leaf['label'], 'ready' => false];
        }

        $route = RouteFacade::getRoutes()->getByName($leaf['route']);

        if ($route === null || ! $this->passes($user, $route)) {
            return null;
        }

        return [
            'label' => $leaf['label'],
            'icon' => $leaf['icon'] ?? null,
            'routeName' => $leaf['route'],
            'badge' => $leaf['badge'] ?? null,
        ];
    }

    private function isNavigable(string $name): bool
    {
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function passes(User $user, Route $route): bool
    {
        $middleware = $route->gatherMiddleware();

        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            if ($entry === 'marketplace' && ! config('features.marketplace')) {
                return false;
            }

            if (str_starts_with($entry, 'access:')) {
                $permission = substr($entry, strlen('access:'));

                if (! $this->access->can($user, $permission)) {
                    return false;
                }
            }

            if (str_starts_with($entry, 'role:')) {
                $roles = explode('|', substr($entry, strlen('role:')));

                if (! $user->hasAnyRole($roles)) {
                    return false;
                }
            }
        }

        return true;
    }
}
