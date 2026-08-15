<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginAnomalyService;
use App\Services\OtpService;
use App\Support\DashboardRouteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly LoginAnomalyService $loginAnomaly,
        private readonly DashboardRouteResolver $dashboard
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status'           => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        // admins always, and other staff roles on a device we do not recognise, must pass otp before a session exists
        if ($user->hasRole('admin') || $this->loginAnomaly->requiresOtp($user, $request)) {
            $this->otpService->generate($user->phone, 'login');

            $request->session()->put('auth.login_identifier', $user->phone);
            $request->session()->put('auth.otp_type', 'login');

            return redirect('/verify-otp');
        }

        Auth::login($user);

        $this->loginAnomaly->checkAndRecord($user, $request); // updates last seen; farmers are skipped inside the service

        $request->session()->regenerate();

        // one shared place decides where each role lands
        return redirect($this->dashboard->path($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
