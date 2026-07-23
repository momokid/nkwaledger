<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $config = config('permissions');

        $permissionNames = [];

        foreach ($config['modules'] as $module => $moduleConfig) {
            foreach (array_keys($moduleConfig['actions']) as $action) {
                $permissionNames[] = "{$module}.{$action}";
            }
        }

        foreach (array_keys($config['standalone']) as $permissionName) {
            $permissionNames[] = $permissionName;
        }

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach ($config['defaults'] as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if ($role) {
                $role->syncPermissions($permissions);
            }
        }
    }
}
