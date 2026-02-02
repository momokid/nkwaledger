<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // 1. Roles
            $roles = [
                'admin' => 'System administrator',
                'supervisor' => 'Supervisor with approval authority',
                'farmer' => 'End user (farmer)',
            ];

            foreach ($roles as $name => $description) {
                DB::table('roles')->updateOrInsert(
                    ['name' => $name],
                    [
                        'description' => $description,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 2. Permissions
            $permissions = [
                'record_income'      => 'Record income transactions',
                'record_expense'     => 'Record expense transactions',
                'record_loss'        => 'Record loss transactions',
                'view_ledger'        => 'View own ledger',
                'view_all_ledgers'   => 'View all ledgers',
                'approve_backdated'  => 'Approve backdated or post-dated transactions',
                'manage_users'       => 'Create, update or deactivate users',
                'assign_roles'       => 'Assign roles to users',
            ];

            foreach ($permissions as $key => $description) {
                DB::table('permissions')->updateOrInsert(
                    ['key' => $key],
                    [
                        'description' => $description,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 3. Attach permissions to roles
            $rolePermissions = [
                'admin' => array_keys($permissions),

                'supervisor' => [
                    'record_income',
                    'record_expense',
                    'record_loss',
                    'view_all_ledgers',
                    'approve_backdated',
                ],

                'farmer' => [
                    'record_income',
                    'record_expense',
                    'record_loss',
                    'view_ledger',
                ],
            ];

            foreach ($rolePermissions as $roleName => $permissionKeys) {

                $roleId = DB::table('roles')->where('name', $roleName)->value('id');

                foreach ($permissionKeys as $permissionKey) {
                    $permissionId = DB::table('permissions')
                        ->where('key', $permissionKey)
                        ->value('id');

                    DB::table('permission_role')->updateOrInsert(
                        [
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                        ],
                        []
                    );
                }
            }
        });
    }
}
