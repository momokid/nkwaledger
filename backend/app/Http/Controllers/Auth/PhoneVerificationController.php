<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LoginAnomalyService;
use App\Services\OtpService;
use App\Services\PhoneVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PhoneVerificationController extends Controller
{
    private const TYPE = 'phone_verification';

    // the session key naming stays clear of the pre-auth login flow's own
    // auth.login_identifier, since this runs for an already-authenticated user
    private const SESSION_KEY = 'phone_verification.identifier';

    public function __construct(
        private readonly OtpService $otpService,
        private readonly PhoneVerificationService $verification,
    ) {}

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        // a tracked staff role defaults to their email, same as login - unless they ask
        // for the sms fallback, or have no email on file to send to in the first place
        $useEmail = ! $request->boolean('sms_fallback')
            && $user->hasAnyRole(LoginAnomalyService::TRACKED_ROLES)
            && $user->email;

        $channel = $useEmail ? 'email' : 'sms';
        $identifier = $channel === 'email' ? $user->email : $user->phone;

        $this->otpService->generate($identifier, self::TYPE, $channel);

        $request->session()->put(self::SESSION_KEY, $identifier);

        return back()->with('status', 'We sent you a code.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $request->user();

        // falls back to the phone when nothing is pending, matching the old,
        // always-sms behaviour for a code seeded without going through send()
        $identifier = $request->session()->get(self::SESSION_KEY, $user->phone);

        $verified = $this->otpService->verify($identifier, $validated['code'], self::TYPE);

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'The code is invalid, expired, or has been used.',
            ]);
        }

        $this->verification->markVerified($user);
        $request->session()->forget(self::SESSION_KEY);

        return back()->with('status', 'Your phone is verified.');
    }
}
