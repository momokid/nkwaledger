<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPermissionDenial;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessControlService
{
    public function can(User $user, string $permission): bool
    {
        if ($this->isDenied($user, $permission)) {
            return false;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    protected function isDenied(User $user, string $permission): bool
    {
        $permissionId = Permission::where('name', $permission)->value('id');

        if (! $permissionId) {
            return false;
        }

        return UserPermissionDenial::where('user_id', $user->id)
            ->where('permission_id', $permissionId)
            ->exists();
    }

    // last-holder safety check, used by make:admin and the permissions screens
    public function isLastHolder(User $user, string $permission): bool
    {
        if (! $this->can($user, $permission)) {
            return false;
        }

        return $this->effectiveHolders($permission)->count() === 1;
    }

    // every user who can *actually* use this permission right now, accounting for denials
    public function effectiveHolders(string $permission): \Illuminate\Support\Collection
    {
        try {
            $candidates = User::permission($permission)->get();
        } catch (PermissionDoesNotExist) {
            return collect();
        }

        return $candidates->filter(fn(User $candidate) => $this->can($candidate, $permission));
    }

    // checks whether removing a permission from a role would leave zero effective holders
    public function roleRemovalWouldEliminateLastHolder(Role $role, string $permission): bool
    {
        if (! $role->hasPermissionTo($permission)) {
            return false;
        }

        $holders = $this->effectiveHolders($permission);

        foreach ($holders as $holder) {
            if ($this->wouldRetainAccessWithoutRole($holder, $role, $permission)) {
                return false;
            }
        }

        return true;
    }

    // checks a pending role reassignment for any permission that only the user's current role provides
    public function roleChangeWouldEliminateLastHolder(User $user, string $newRoleName): ?string
    {
        $currentRole = $user->roles->first();

        if (! $currentRole) {
            return null;
        }

        $newRole = Role::where('name', $newRoleName)->first();
        $newRolePermissions = $newRole?->permissions->pluck('name') ?? collect();

        $lostPermissions = $currentRole->permissions->pluck('name')->diff($newRolePermissions);

        foreach ($lostPermissions as $permissionName) {
            if ($user->hasDirectPermission($permissionName)) {
                continue;
            }

            if ($this->isLastHolder($user, $permissionName)) {
                return $permissionName;
            }
        }

        return null;
    }

    // does this holder have another path to the permission besides the role being edited?
    protected function wouldRetainAccessWithoutRole(User $holder, Role $role, string $permission): bool
    {
        if ($holder->hasDirectPermission($permission)) {
            return true;
        }

        foreach ($holder->roles as $userRole) {
            if ($userRole->id !== $role->id && $userRole->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }
}
