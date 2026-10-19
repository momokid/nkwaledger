<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ussd\UssdPinReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// an admin sets an agent's temporary USSD PIN, after checking who is asking
class StaffUssdPinController extends Controller
{
    public function __construct(private readonly UssdPinReset $reset) {}

    public function store(Request $request, User $user): JsonResponse
    {
        abort_unless($user->hasRole('agent'), 404);

        return response()->json(['pin' => $this->reset->reset($user, $request->user())]);
    }
}
