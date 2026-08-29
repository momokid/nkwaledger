<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFarmerGroupRequest;
use App\Http\Requests\UpdateFarmerGroupRequest;
use App\Models\Community;
use App\Models\FarmerGroup;
use App\Models\FarmerGroupType;
use App\Models\Region;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FarmerGroupController extends Controller
{
    public function __construct(private AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/FarmerGroups/Index', [
            'farmerGroups' => FarmerGroup::query()
                ->with(['groupType', 'region', 'district', 'community'])
                ->orderBy('name')
                ->paginate(15),
            // group types and regions have no parent, so they're preloaded in full — districts and
            // communities are fetched on demand as the region/district selection cascades
            'groupTypes' => FarmerGroupType::query()->orderBy('name')->get(['id', 'name']),
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'permissions' => [
                'create' => $this->access->can($user, 'farmer-groups.create'),
                'update' => $this->access->can($user, 'farmer-groups.update'),
                'delete' => $this->access->can($user, 'farmer-groups.delete'),
            ],
        ]);
    }

    // narrows the group picker once a community is chosen, since a group belongs to one community
    public function byCommunity(Community $community): JsonResponse
    {
        return response()->json(
            FarmerGroup::query()
                ->where('community_id', $community->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    public function store(StoreFarmerGroupRequest $request): RedirectResponse
    {
        FarmerGroup::create($request->validated());

        return back()->with('success', 'Farmer group created.');
    }

    public function update(UpdateFarmerGroupRequest $request, FarmerGroup $farmerGroup): RedirectResponse
    {
        $farmerGroup->update($request->validated());

        return back()->with('success', 'Farmer group updated.');
    }

    public function destroy(FarmerGroup $farmerGroup): RedirectResponse
    {
        $farmerGroup->delete();

        return back()->with('success', 'Farmer group deleted.');
    }
}
