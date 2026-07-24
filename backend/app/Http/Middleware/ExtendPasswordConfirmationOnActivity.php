<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtendPasswordConfirmationOnActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');

        // only extends a confirmation that hasn't already expired, so genuine inactivity still times out
        if ($confirmedAt !== null) {
            $timeout = config('auth.password_timeout', 1800);

            if (now()->timestamp - $confirmedAt < $timeout) {
                $request->session()->put('auth.password_confirmed_at', now()->timestamp);
            }
        }

        return $next($request);
    }
}
