<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpLoginRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LoginAnomalyService;
use App\Services\OtpService;
use App\Services\PhoneVerificationService;
use App\Support\OtpOutcomeResolver;
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
        private readonly OtpOutcomeResolver $outcome,
        private readonly PhoneVerificationService $verification,
        private readonly AuditService $audit,
    ) {}

    public function create(Request $request): Response
    {
        // only the purpose reaches the browser; the number stays on the server
        return Inertia::render('Auth/VerifyOtp', [
            'type' => $request->session()->get('auth.otp_type'),
        ]);
    }

    public function requestLogin(OtpLoginRequest $request): RedirectResponse
    {
        // the request has already cleaned this, so the lookup, the code and the session all share one spelling
        $phone = $request->validated()['phone'];

        $user = User::where('phone', $phone)->first();

        // a code only goes out to a real account, but the reply looks the same either way
        if ($user) {
            $this->otpService->generate($phone, 'login');
        }

        $request->session()->put('auth.login_identifier', $phone);
        $request->session()->put('auth.otp_type', 'login');

        return redirect('/verify-otp');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        // the step before this decided who is verifying; the browser cannot change it
        $identifier = $request->session()->get('auth.login_identifier');
        $type       = $request->session()->get('auth.otp_type');

        $verified = $this->otpService->verify($identifier, $validated['code'], $type);

        if (! $verified) {
            $this->audit->record('otp.failed', ['identifier' => $identifier, 'type' => $type]);

            throw ValidationException::withMessages([
                'code' => 'The code is invalid, expired, or has been used.',
            ]);
        }

        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user  = User::where($field, $identifier)->first();

        // one place decides what a verified code of each type actually means
        if ($user && $this->outcome->authenticates($type, $user)) {
            Auth::login($user);
            $this->audit->recordSignIn($user);
            $this->loginAnomaly->checkAndRecord($user, $request);
            $request->session()->regenerate();

            if ($this->outcome->verifiesPhone($type, $user)) {
                $this->verification->markVerified($user);
            }
        }

        // an invited person is not signed in, so the next step is told who it is acting for
        if ($user && ! $this->outcome->authenticates($type, $user)) {
            $request->session()->put('auth.activating_user_id', $user->id);
        }

        $request->session()->forget(['auth.login_identifier', 'auth.otp_type']);

        return redirect($this->outcome->path($type, $user));
    }

    public function resend(Request $request): RedirectResponse
    {
        // same source of truth as the check itself
        $identifier = $request->session()->get('auth.login_identifier');
        $type       = $request->session()->get('auth.otp_type');

        if (! $identifier || ! $type) {
            return back();
        }

        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // the step before this sets a session for any number, so without this check
        // resend would send an sms to whatever a stranger typed
        if (User::where($field, $identifier)->exists()) {
            $this->otpService->generate($identifier, $type);
        }

        // silent either way, so this cannot be used to find out which numbers are registered
        return back();
    }
}
