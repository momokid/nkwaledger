<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\PhoneNormalizer;

class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/VerifyOtp', [
            'identifier' => $request->session()->get('auth.login_identifier', ''),
            'type'       => $request->session()->get('auth.otp_type', 'registration'),
        ]);
    }

    public function requestLogin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $phone = PhoneNormalizer::normalize($validated['phone']);
        $user  = User::where('phone', $phone)->first();

        if ($user) {
            $this->otpService->generate($phone, 'login');
            $request->session()->put('auth.login_identifier', $phone);
            $request->session()->put('auth.otp_type', 'login');
        }

        return redirect('/verify-otp');
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

        if (! $user) {
            $field = filter_var($validated['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            $user  = User::where($field, $validated['identifier'])->first();

            if ($user) {
                Auth::login($user);
                $request->session()->regenerate();
            }
        }

        if ($user && ! $user->is_phone_verified) {
            $user->update(['is_phone_verified' => true]);
        }

        return redirect($this->dashboardFor($user));
    }

    public function resend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
            'type'       => ['required', 'string', 'in:registration,login,password_reset'],
        ]);

        $this->otpService->generate($validated['identifier'], $validated['type']);

        return back();
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
