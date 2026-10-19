<?php

namespace App\Http\Controllers\Ussd;

use App\Http\Controllers\Controller;
use App\Services\Ussd\UssdFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// a phone-style page for trying USSD by hand. The routes refuse everywhere but local and testing
class UssdSimulatorController extends Controller
{
    public function __construct(private readonly UssdFlow $flow) {}

    public function page(): View
    {
        $this->localOnly();

        return view('dev.ussd');
    }

    private function localOnly(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
    }

    public function dial(Request $request): JsonResponse
    {
        $this->localOnly();

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'session' => ['required', 'string', 'max:100'],
            'new' => ['nullable', 'boolean'],
            'input' => ['nullable', 'string', 'max:20'],
        ]);

        $reply = $this->flow->handle($data['session'], $data['phone'], (bool) ($data['new'] ?? false), (string) ($data['input'] ?? ''));

        return response()->json(['message' => $reply['message'], 'continueSession' => $reply['continue']]);
    }
}
