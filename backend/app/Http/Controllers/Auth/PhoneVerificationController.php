<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use App\Services\PhoneVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PhoneVerificationController extends Controller
{
    private const TYPE = 'phone_verification';

    public function __construct(
        private readonly OtpService $otpService,
        private readonly PhoneVerificationService $verification,
    ) {}

    public function send(Request $request): RedirectResponse
    {
        // the number comes from the account, never from the form
        $this->otpService->generate($request->user()->phone, self::TYPE);

        return back()->with('status', 'We sent a code to your phone.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $request->user();

        $verified = $this->otpService->verify($user->phone, $validated['code'], self::TYPE);

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'The code is invalid, expired, or has been used.',
            ]);
        }

        $this->verification->markVerified($user);

        return back()->with('status', 'Your phone is verified.');
    }
}
