<?php

namespace App\Http\Controllers\Ussd;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Services\Ussd\UssdPinReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// the farmer's own agent sets a temporary USSD PIN, once they are sure who is asking
class UssdPinResetController extends Controller
{
    public function __construct(private readonly UssdPinReset $reset) {}

    public function store(Request $request, FarmerProfile $farmer): JsonResponse
    {
        abort_if((int) $farmer->assigned_agent_id !== $request->user()->id, 404);

        return response()->json(['pin' => $this->reset->reset($farmer->user, $request->user())]);
    }
}
