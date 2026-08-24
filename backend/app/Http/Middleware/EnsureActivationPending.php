<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivationPending
{
    // only someone who just proved an invitation code may set a password this way
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get('auth.activating_user_id');

        if ($id === null) {
            return redirect('/login');
        }

        $user = User::find($id);

        // the row may have gone, or the account may already be active, in which case this is not an activation
        if (! $user || $user->password !== null) {
            $request->session()->forget('auth.activating_user_id');

            return redirect('/login');
        }

        return $next($request);
    }
}
