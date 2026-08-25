<?php

$user = \App\Models\User::find(2);

dump([
    'roles' => $user->getRoleNames()->all(),
    'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
    'denials' => \Illuminate\Support\Facades\DB::table('user_permission_denials')
        ->where('user_id', $user->id)
        ->pluck('permission_id')
        ->all(),
    'permission_exists' => \Spatie\Permission\Models\Permission::where('name', 'transaction-templates.view')->exists(),
]);
