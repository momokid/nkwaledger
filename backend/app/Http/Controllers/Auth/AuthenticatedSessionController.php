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

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

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

        if (! $user->is_phone_verified) {
            $identifier = $user->phone ?? $user->email;

            $this->otpService->generate($identifier, 'login');

            $request->session()->put('auth.login_identifier', $identifier);
            $request->session()->put('auth.otp_type', 'login');

            return redirect('/verify-otp');
        }

        Auth::login($user);

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
