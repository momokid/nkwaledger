<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index(): Response
    {
        $permissions = Permission::all();

        $roles = Role::with('permissions')->get()->map(fn(Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'permission_ids' => $role->permissions->pluck('id'),
        ]);

        return Inertia::render('Admin/Permissions/Roles', [
            'roles' => $roles,
            'modules' => $this->buildModulesPayload($permissions),
            'standalone' => $this->buildStandalonePayload($permissions),
        ]);
    }

    public function update(Request $request, Role $role, AccessControlService $accessControl): RedirectResponse
    {
        $validated = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $newPermissionIds = $validated['permission_ids'] ?? [];
        $newPermissions = Permission::whereIn('id', $newPermissionIds)->pluck('name')->all();

        $currentPermissions = $role->permissions->pluck('name')->all();
        $removedPermissions = array_diff($currentPermissions, $newPermissions);

        foreach ($removedPermissions as $permissionName) {
            if ($accessControl->roleRemovalWouldEliminateLastHolder($role, $permissionName)) {
                return back()->withErrors([
                    'permission_ids' => sprintf(
                        'Removing "%s" from this role would leave no one able to use it.',
                        $this->labelFor($permissionName)
                    ),
                ]);
            }
        }

        $role->syncPermissions($newPermissions);

        return back()->with('success', 'Role permissions updated.');
    }

    // turns the module/action config into id+label pairs the browser is allowed to see
    protected function buildModulesPayload($permissions): array
    {
        $payload = [];

        foreach (config('permissions.modules') as $module => $moduleConfig) {
            $items = [];

            foreach ($moduleConfig['actions'] as $action => $actionLabel) {
                $permission = $permissions->firstWhere('name', "{$module}.{$action}");

                if ($permission) {
                    $items[] = ['id' => $permission->id, 'label' => $actionLabel];
                }
            }

            $payload[] = ['label' => $moduleConfig['label'], 'permissions' => $items];
        }

        return $payload;
    }

    // same id+label treatment for the standalone permissions list
    protected function buildStandalonePayload($permissions): array
    {
        $payload = [];

        foreach (config('permissions.standalone') as $permissionName => $label) {
            $permission = $permissions->firstWhere('name', $permissionName);

            if ($permission) {
                $payload[] = ['id' => $permission->id, 'label' => $label];
            }
        }

        return $payload;
    }

    // resolves a raw permission name back to its human label, for error messages only
    protected function labelFor(string $permissionName): string
    {
        $config = config('permissions');

        if (isset($config['standalone'][$permissionName])) {
            return $config['standalone'][$permissionName];
        }

        [$module, $action] = array_pad(explode('.', $permissionName, 2), 2, null);

        return $config['modules'][$module]['actions'][$action] ?? $permissionName;
    }
}
