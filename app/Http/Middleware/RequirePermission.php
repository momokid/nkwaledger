<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Auth\AuthorizationService;
use App\Exceptions\AuthorizationException;

class RequirePermission
{
    public function __construct(
        private AuthorizationService $auth
    ) {}

    public function handle(Request $request, Closure $next, string $permission)
    {
        if (!$this->auth->can($request->user(), $permission)) {
            throw new AuthorizationException();
        }

        return $next($request);
    }
}
