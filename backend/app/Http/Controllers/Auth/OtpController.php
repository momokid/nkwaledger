<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function create(): Response
    {
        return Inertia::render('Auth/VerifyOtp');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
            'code'       => ['required', 'string', 'digits:6'],
            'type'       => ['required', 'string', 'in:registration,login,password_reset'],
        ]);

        $verified = $this->otpService->verify(
            $validated['identifier'],
            $validated['code'],
            $validated['type'],
        );

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'The code is invalid, expired, or has been used.',
            ]);
        }

        $user = Auth::user();

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
}
