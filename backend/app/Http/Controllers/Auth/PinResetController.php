<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

// the PIN itself lives only on the phone; this only proves the person holding it owns the number
// on record. It acts on the signed-in user alone, so it can say nothing about any other number.
class PinResetController extends Controller
{
    private const TYPE = 'pin_reset';

    public function __construct(
        private readonly OtpService $otp,
        private readonly AuditService $audit,
    ) {}

    public function send(Request $request): JsonResponse
    {
        $phone = $request->user()->phone;

        $this->audit->record('pin.reset_requested');

        if ($phone === null) {
            return response()->json(['status' => 'failed'], 503);
        }

        // asked again inside the cooldown: the code already sent is still the one to use
        if (! $this->otp->sentWithin($phone, self::TYPE, config('otp.pin_reset.cooldown_seconds'))) {
            try {
                $this->otp->generate($phone, self::TYPE);
            } catch (Throwable $e) {
                report($e);

                return response()->json(['status' => 'failed'], 503);
            }
        }

        return response()->json(['status' => 'sent']);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $result = $this->otp->check((string) $request->user()->phone, $validated['code'], self::TYPE);

        if ($result === 'ok') {
            $this->audit->record('pin.reset');

            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => match ($result) {
            'wrong' => 'wrong',
            'exhausted' => 'too_many',
            default => 'expired',
        }], 422);
    }
}
