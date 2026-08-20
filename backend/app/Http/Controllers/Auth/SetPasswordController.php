<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetPasswordRequest;
use App\Models\User;
use App\Services\PhoneVerificationService;
use App\Support\DashboardRouteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SetPasswordController extends Controller
{
    public function __construct(
        private readonly PhoneVerificationService $verification,
        private readonly DashboardRouteResolver $dashboard,
    ) {}

    public function create(Request $request): Response
    {
        $user = User::find($request->session()->get('auth.activating_user_id'));

        // enough to greet them by name, nothing else about the account
        return Inertia::render('Auth/SetPassword', [
            'firstName' => $user->first_name,
        ]);
    }

    public function store(SetPasswordRequest $request): RedirectResponse
    {
        $user = User::find($request->session()->get('auth.activating_user_id'));

        $user->update(['password' => Hash::make($request->validated()['password'])]);

        // finishing activation is what proves they hold the phone the code went to
        $this->verification->markVerified($user);

        // the pass is spent, so a shared browser cannot replay it
        $request->session()->forget('auth.activating_user_id');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($this->dashboard->path($user))
            ->with('success', 'Your account is ready. Welcome to NkwaLedger.');
    }
}
