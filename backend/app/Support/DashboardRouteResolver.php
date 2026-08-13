<?php

namespace App\Support;

use App\Models\User;

class DashboardRouteResolver
{
    // each role has its own home page
    private const ROUTES = [
        'admin'    => 'admin.dashboard',
        'agent'    => 'agent.dashboard',
        'vet'      => 'vet.dashboard',
        'adviser'  => 'adviser.dashboard',
        'supplier' => 'supplier.dashboard',
    ];

    // gives back the route name for this user's role
    public function routeName(?User $user): string
    {
        foreach (self::ROUTES as $role => $route) {
            if ($user?->hasRole($role)) {
                return $route;
            }
        }

        return 'farmer.dashboard';
    }

    // gives back the web address instead of the name
    public function path(?User $user): string
    {
        return route($this->routeName($user), absolute: false);
    }
}
