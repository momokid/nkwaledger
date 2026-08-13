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
        private readonly LoginAnomalyService $loginAnomaly, // checks and alerts on unrecognized devices for admins and agents
        private readonly DashboardRouteResolver $dashboard,
        private readonly PhoneVerificationService $verification,
    ) {}

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
                $this->loginAnomaly->checkAndRecord($user, $request); // no-op for roles outside admin/agent
                $request->session()->regenerate();
            }
        }

        // a login code proves they hold the phone, so it counts as verification
        if ($user && $validated['type'] === 'login') {
            $this->verification->markVerified($user);
        }

        // one shared place decides where each role lands
        return redirect($this->dashboard->path($user));
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
}
