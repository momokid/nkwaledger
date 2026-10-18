<?php

namespace App\Http\Controllers\Ussd;

use App\Http\Controllers\Controller;
use App\Services\Ussd\UssdFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// the gateway's callback. Field names are the common USSD ones; Arkesel's exact names go here once confirmed
class UssdController extends Controller
{
    public function __construct(private readonly UssdFlow $flow) {}

    public function callback(Request $request, string $secret): JsonResponse
    {
        $expected = (string) config('services.ussd.secret');

        abort_if($expected === '' || ! hash_equals($expected, $secret), 404);

        $data = $request->validate([
            'sessionID' => ['required', 'string', 'max:100'],
            'msisdn' => ['required', 'string', 'max:20'],
            'newSession' => ['nullable', 'boolean'],
            'userData' => ['nullable', 'string', 'max:20'],
        ]);

        $reply = $this->flow->handle($data['sessionID'], $data['msisdn'], (bool) ($data['newSession'] ?? false), (string) ($data['userData'] ?? ''));

        return response()->json([
            'sessionID' => $data['sessionID'],
            'msisdn' => $data['msisdn'],
            'message' => $reply['message'],
            'continueSession' => $reply['continue'],
        ]);
    }
}
