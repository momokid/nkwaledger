<?php

namespace App\Http\Controllers\Ussd;

use App\Http\Controllers\Controller;
use App\Services\Auth\OtpService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\Request;

class UssdAuthController extends Controller
{
    /**
     * Handle incoming USSD authentication request.
     *
     * Expected fields (provider-agnostic):
     * - sessionId
     * - phoneNumber
     * - text
     */
    public function handle(Request $request, OtpService $otpService, AuditLogger $audit)
    {
        $phone = $request->input('phoneNumber');
        $text  = trim($request->input('text', ''));

        /**
         * USSD FLOW:
         * text == ""      → initial request
         * text == "1"     → request OTP
         */

        if ($text === '') {
            return response(
                "CON Welcome to NkwaLedger\n1. Login",
                200,
                ['Content-Type' => 'text/plain']
            );
        }

        if ($text === '1') {
            // Generate OTP
            $otpService->generate(
                phoneNumber: $phone,
                channel: 'ussd',
                ipAddress: $request->ip(),
                userAgent: 'USSD'
            );

            // Audit trail
            $audit->log(
                eventType: 'otp_requested',
                phoneNumber: $phone,
                ipAddress: $request->ip(),
                userAgent: 'USSD',
                meta: ['channel' => 'ussd']
            );

            return response(
                "END An OTP has been sent to your phone.\nUse it to log in.",
                200,
                ['Content-Type' => 'text/plain']
            );
        }

        return response(
            "END Invalid option",
            200,
            ['Content-Type' => 'text/plain']
        );
    }
}
