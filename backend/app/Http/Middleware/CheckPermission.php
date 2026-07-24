<?php

namespace App\Http\Middleware;

use App\Services\AccessControlService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(private AccessControlService $accessControl) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $this->accessControl->can($request->user(), $permission)) {
            abort(403);
        }

        return $next($request);
    }
}
