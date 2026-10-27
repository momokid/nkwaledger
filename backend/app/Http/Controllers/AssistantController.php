<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __invoke(Request $request, AssistantService $assistant): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:500']]);
        $user = $request->user();
        $support = ['support_phone' => AppSetting::value('support_phone')];

        if ($assistant->limitReached($user)) {
            return response()->json($support, 429);
        }

        $reply = $assistant->ask($user, $data['message']);

        return $reply === null
            ? response()->json($support, 503)
            : response()->json(['reply' => $reply]);
    }
}
