<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginAnomalyService;
use App\Services\OtpService;
use App\Services\PhoneVerificationService;
use App\Support\DashboardRouteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly LoginAnomalyService $loginAnomaly, // records the device and alerts on a first sighting
        private readonly DashboardRouteResolver $dashboard,
        private readonly PhoneVerificationService $verification,
    ) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/VerifyOtp', [
            'type'   => $request->session()->get('auth.otp_type'),
            'masked' => $this->mask($request->session()->get('auth.login_identifier')),
        ]);
    }

    public function requestLogin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $user = User::where('phone', $validated['phone'])->first();

        // a code only goes out to a real account, but the reply looks the same either way
        if ($user) {
            $this->otpService->generate($validated['phone'], 'login');
        }

        $request->session()->put('auth.login_identifier', $validated['phone']);
        $request->session()->put('auth.otp_type', 'login');

        return redirect('/verify-otp');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        // the step before this decided who is logging in; the browser cannot change it
        $identifier = $request->session()->get('auth.login_identifier');
        $type       = $request->session()->get('auth.otp_type');

        $verified = $this->otpService->verify($identifier, $validated['code'], $type);

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'The code is invalid, expired, or has been used.',
            ]);
        }

        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user  = User::where($field, $identifier)->first();

        if ($user) {
            Auth::login($user);
            $this->loginAnomaly->checkAndRecord($user, $request);
            $request->session()->regenerate();

            // a login code proves they hold the phone, so it counts as verification
            if ($type === 'login') {
                $this->verification->markVerified($user);
            }
        }

        $request->session()->forget(['auth.login_identifier', 'auth.otp_type']);

        // one shared place decides where each role lands
        return redirect($this->dashboard->path($user));
    }

    public function resend(Request $request): RedirectResponse
    {
        // same source of truth as the check itself
        $this->otpService->generate(
            $request->session()->get('auth.login_identifier'),
            $request->session()->get('auth.otp_type'),
        );

        return back();
    }

    // enough for someone to recognise their own number, not enough to learn a stranger's
    private function mask(?string $identifier): ?string
    {
        if (! $identifier) {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            [$name, $domain] = explode('@', $identifier, 2);

            return substr($name, 0, 1) . '••••@' . $domain;
        }

        return '•••• ' . substr($identifier, -4);
    }
}
