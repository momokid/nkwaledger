<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOtpPending
{
    // the otp screen only exists as part of a login or registration already under way
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();

        if (! $session->has('auth.login_identifier') || ! $session->has('auth.otp_type')) {
            return redirect('/login');
        }

        return $next($request);
    }
}
