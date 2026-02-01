<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    
    public function handle(Request $request, Closure $next, string $permissionKey): Response
    {
        $user = $request->user();

        // 1. Must be authenticated
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // 2. Must have a role
        if (!$user->role_id) {
            abort(403, 'No role assigned.');
        }

        // 3. Check permission via role
        $hasPermission = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permission_role.role_id', $user->role_id)
            ->where('permissions.key', $permissionKey)
            ->exists();

        if (!$hasPermission) {
            abort(403, 'Permission denied.');
        }

        return $next($request);
    }
}
