<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OtpAuthenticationController extends Controller
{
    /**
     * Request an OTP for a phone number.
     */
    public function requestOtp(Request $request, OtpService $otpService)
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        $otp = $otpService->generate(
            phoneNumber: $validated['phone_number'],
            channel: 'web',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        /**
         * IMPORTANT:
         * We return OTP only for development/testing.
         * This will be REMOVED when SMS is integrated.
         */
        return response()->json([
            'message' => 'OTP generated successfully',
            'otp' => $otp,
        ]);
    }

    /**
     * Verify OTP and authenticate user.
     */
    public function verifyOtp(Request $request, OtpService $otpService)
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'digits:6'],
        ]);

        $isValid = $otpService->verify(
            $validated['phone_number'],
            $validated['otp']
        );

        if (!$isValid) {
            return response()->json([
                'message' => 'Invalid or expired OTP',
            ], 422);
        }

        // Find or fail user by phone number
        $user = User::where('phone_number', $validated['phone_number'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'No user found for this phone number',
            ], 404);
        }

        Auth::login($user);

        return response()->json([
            'message' => 'Authenticated successfully',
        ]);
    }
}
