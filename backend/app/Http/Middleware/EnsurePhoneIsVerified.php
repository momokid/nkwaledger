<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneIsVerified
{
    // the only routes an unverified user may touch
    private const ALLOWED = [
        'dashboard',
        'logout',
        'profile.show',
    ];

    // route name groups that are also allowed
    private const ALLOWED_PREFIXES = [
        'verification.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->phone_verified_at !== null) {
            return $next($request);
        }

        if ($this->isAllowed($request->route()?->getName())) {
            return $next($request);
        }

        return redirect()
            ->route('dashboard')
            ->with('error', 'Please verify your phone number to continue.');
    }

    // checks the route name against the allow list
    private function isAllowed(?string $name): bool
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

        return false;
    }
}
