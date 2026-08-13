<?php

namespace App\Http\Middleware;

use App\Support\DashboardRouteResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneIsVerified
{
    public function __construct(
        private readonly DashboardRouteResolver $dashboard
    ) {}

    // routes an unverified user may still use
    private const ALLOWED = [
        'logout',
    ];

    // whole groups that stay open
    private const ALLOWED_PREFIXES = [
        'otp.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->phone_verified_at !== null) {
            return $next($request);
        }

        $name = $request->route()?->getName();

        if ($this->isAllowed($name, $user)) {
            return $next($request);
        }

        return redirect()
            ->route($this->dashboard->routeName($user))
            ->with('error', 'Please verify your phone number to continue.');
    }

    // says whether this route name is open to an unverified user
    private function isAllowed(?string $name, $user): bool
    {
        if ($name === null) {
            return false;
        }

        if (in_array($name, self::ALLOWED, true)) {
            return true;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        // their own dashboard stays open so they can see the verify button
        return $name === $this->dashboard->routeName($user);
    }
}
