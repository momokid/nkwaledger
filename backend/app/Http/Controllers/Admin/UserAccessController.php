<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPermissionDenial;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserAccessController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->string('q')->trim()->toString();

        $results = [];

        if ($query !== '') {
            $results = User::query()
                ->where('phone', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->limit(20)
                ->get()
                ->map(fn(User $user) => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'surname' => $user->surname,
                    'phone' => $user->phone,
                    'email' => $user->email,
                ]);
        }

        return Inertia::render('Admin/Permissions/Users', [
            'query' => $query,
            'results' => $results,
        ]);
    }

    public function show(User $user): Response
    {
        $permissions = Permission::all();
        $deniedPermissionIds = UserPermissionDenial::where('user_id', $user->id)->pluck('permission_id')->all();

        return Inertia::render('Admin/Permissions/UserDetail', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'surname' => $user->surname,
                'phone' => $user->phone,
                'email' => $user->email,
            ],
            'currentRole' => $user->roles->first()?->name,
            'roles' => Role::pluck('name'),
            'modules' => $this->buildModulesPayload($user, $permissions, $deniedPermissionIds),
            'standalone' => $this->buildStandalonePayload($user, $permissions, $deniedPermissionIds),
        ]);
    }

    // swaps a user's single role for a new one, guarded by the last-holder safety check
    public function updateRole(Request $request, User $user, AccessControlService $accessControl): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $lostPermission = $accessControl->roleChangeWouldEliminateLastHolder($user, $validated['role']);

        if ($lostPermission) {
            return back()->withErrors([
                'role' => sprintf(
                    'Changing this role would leave no one able to use "%s".',
                    $this->labelFor($lostPermission)
                ),
            ]);
        }

        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'Role updated.');
    }

    // grants a permission directly to a user; clears any denial on the same permission so the two states can't coexist
    public function storeGrant(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $permission = Permission::find($validated['permission_id']);

        $user->givePermissionTo($permission);

        UserPermissionDenial::where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->delete();

        return back()->with('success', 'Permission granted.');
    }

    // removes a direct grant, guarded by the last-holder safety check
    public function destroyGrant(User $user, Permission $permission, AccessControlService $accessControl): RedirectResponse
    {
        if ($accessControl->isLastHolder($user, $permission->name)) {
            return back()->withErrors([
                'permission' => sprintf(
                    'Removing this grant would leave no one able to use "%s".',
                    $this->labelFor($permission->name)
                ),
            ]);
        }

        $user->revokePermissionTo($permission);

        return back()->with('success', 'Grant removed.');
    }

    // denies a permission for a user, guarded by the last-holder check, and clears any conflicting direct grant
    public function storeDenial(Request $request, User $user, AccessControlService $accessControl): RedirectResponse
    {
        $validated = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $permission = Permission::find($validated['permission_id']);

        if ($accessControl->isLastHolder($user, $permission->name)) {
            return back()->withErrors([
                'permission' => sprintf(
                    'Denying "%s" would leave no one able to use it.',
                    $this->labelFor($permission->name)
                ),
            ]);
        }

        if ($user->hasDirectPermission($permission)) {
            $user->revokePermissionTo($permission);
        }

        UserPermissionDenial::create([
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'denied_by' => auth()->id(),
        ]);

        return back()->with('success', 'Permission denied.');
    }

    // removes a denial, restoring whatever the user's role would normally grant
    public function destroyDenial(User $user, Permission $permission): RedirectResponse
    {
        UserPermissionDenial::where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->delete();

        return back()->with('success', 'Denial removed.');
    }

    protected function stateFor(User $user, Permission $permission, array $deniedPermissionIds): string
    {
        if (in_array($permission->id, $deniedPermissionIds, true)) {
            return 'deny';
        }

        if ($user->hasDirectPermission($permission)) {
            return 'grant';
        }

        return 'default';
    }

    protected function buildModulesPayload(User $user, Collection $permissions, array $deniedPermissionIds): array
    {
        $payload = [];

        foreach (config('permissions.modules') as $module => $moduleConfig) {
            $items = [];

            foreach ($moduleConfig['actions'] as $action => $actionLabel) {
                $permission = $permissions->firstWhere('name', "{$module}.{$action}");

                if ($permission) {
                    $items[] = [
                        'id' => $permission->id,
                        'label' => $actionLabel,
                        'state' => $this->stateFor($user, $permission, $deniedPermissionIds),
                    ];
                }
            }

            $payload[] = ['label' => $moduleConfig['label'], 'permissions' => $items];
        }

        return $payload;
    }

    protected function buildStandalonePayload(User $user, Collection $permissions, array $deniedPermissionIds): array
    {
        $payload = [];

        foreach (config('permissions.standalone') as $permissionName => $label) {
            $permission = $permissions->firstWhere('name', $permissionName);

            if ($permission) {
                $payload[] = [
                    'id' => $permission->id,
                    'label' => $label,
                    'state' => $this->stateFor($user, $permission, $deniedPermissionIds),
                ];
            }
        }

        return $payload;
    }

    // resolves a raw permission name back to its human label, for error messages only — same helper as RolePermissionController
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
