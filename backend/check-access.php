<?php

echo "USERS" . PHP_EOL;

foreach (\App\Models\User::with('roles')->get() as $u) {
    echo "  {$u->id}  {$u->phone}  [" . $u->roles->pluck('name')->implode(', ') . "]" . PHP_EOL;
}

echo PHP_EOL . "ROLES" . PHP_EOL;

foreach (\Spatie\Permission\Models\Role::with('permissions')->get() as $r) {
    echo "  {$r->name} (" . $r->permissions->count() . ")" . PHP_EOL;
    echo "    " . ($r->permissions->pluck('name')->implode(', ') ?: 'none') . PHP_EOL;
}

echo PHP_EOL . "PERMISSIONS IN DB: " . \Spatie\Permission\Models\Permission::count() . PHP_EOL;
