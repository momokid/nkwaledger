<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

class NavigationAccessService
{
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

            if ($name === null || ! str_starts_with($name, 'admin.')) {
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

    private function passes(User $user, Route $route): bool
    {
        $middleware = $route->gatherMiddleware();

        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                continue;
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
