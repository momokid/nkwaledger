<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPermissionDenial;
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

    // decides whether a permission is left at its role default, directly granted, or denied for this user
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

    // groups permissions by module, same shape as the role matrix, with a per-user state attached to each one
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

    // same id+label+state treatment for the standalone permissions list
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
}
