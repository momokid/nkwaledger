<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\FarmerGroup;
use App\Services\FarmerGroupLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmerGroupLookupController extends Controller
{
    public function __construct(private readonly FarmerGroupLookupService $lookup) {}

    public function index(Request $request): JsonResponse
    {
        $communityId = $request->integer('community_id') ?: null;

        return response()->json(['data' => $this->lookup->groupsFor($request->user(), $communityId)]);
    }

    public function show(Request $request, FarmerGroup $farmerGroup): JsonResponse
    {
        $detail = $this->lookup->detail($request->user(), $farmerGroup);

        abort_if($detail === null, 404);

        return response()->json($detail);
    }
}
