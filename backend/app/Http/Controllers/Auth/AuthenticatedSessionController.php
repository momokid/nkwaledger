<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\LoginAnomalyService;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly LoginAnomalyService $loginAnomaly
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

        // admins must complete an otp step before their session is created, regardless of which tab they logged in from
        if ($user->hasRole('admin')) {
            $this->otpService->generate($user->phone, 'login');

            $request->session()->put('auth.login_identifier', $user->phone);
            $request->session()->put('auth.otp_type', 'login');

            return redirect('/verify-otp');
        }

        Auth::login($user);

        $this->loginAnomaly->checkAndRecord($user, $request); // no-op for roles outside admin/agent

        $request->session()->regenerate();

        return redirect($this->dashboardFor($user));
    }

    private function dashboardFor(mixed $user): string
    {
        return match (true) {
            $user?->hasRole('admin')    => '/admin/dashboard',
            $user?->hasRole('agent')    => '/agent/dashboard',
            $user?->hasRole('vet')      => '/vet/dashboard',
            $user?->hasRole('adviser')  => '/adviser/dashboard',
            $user?->hasRole('supplier') => '/supplier/dashboard',
            default                     => '/farmer/dashboard',
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
