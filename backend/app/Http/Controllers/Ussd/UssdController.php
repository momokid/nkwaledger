<?php

namespace App\Http\Controllers\Ussd;

use App\Http\Controllers\Controller;
use App\Services\Ussd\UssdFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// the gateway's callback, in Arkesel's documented shape: sessionID, userID, newSession, msisdn, userData in; the same plus message and continueSession out
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
            // a USSD reply can run to about 180 characters; the dial string on a new session is ignored
            'userData' => ['nullable', 'string', 'max:182'],
            'userID' => ['nullable', 'string', 'max:100'],
        ]);

        $reply = $this->flow->handle($data['sessionID'], $data['msisdn'], (bool) ($data['newSession'] ?? false), (string) ($data['userData'] ?? ''));

        return response()->json([
            'sessionID' => $data['sessionID'],
            'userID' => $data['userID'] ?? null,
            'msisdn' => $data['msisdn'],
            'message' => $reply['message'],
            'continueSession' => $reply['continue'],
        ]);
    }
}
