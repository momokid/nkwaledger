<?php

namespace App\Services\Auth;

use App\Models\User;

class AuthorizationService
{
    /**
     * Check if the user has the specified role.
     */
    public function can(User $user, string $permission, array $context = []): bool
    {

        $role = $user->role;

        $permissions = config("rbac.permissions.$role", []);

        //Direct peermission
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        //context-aware permissions - future use
        if ($permission == "view_own_ledger") {
            return $context['owner_id'] ?? null === $user->id;
        }

        return false;
    }
}
