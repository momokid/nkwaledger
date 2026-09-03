<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FarmUnitRequest;
use App\Http\Requests\Admin\StoreFarmUnitFromListRequest;
use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FarmUnitController extends Controller
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly AuditService $audit,
    ) {}

    // every unit across the farmers this person can reach
    public function all(Request $request): Response
    {
        $user = $request->user();
        $farmerUuid = $request->query('farmer');

        $units = FarmUnit::query()
            ->whereIn('farmer_profile_id', $this->reachableFarmerIds($user))
            ->when($farmerUuid, fn(Builder $query) => $query->whereHas(
                'farmerProfile',
                fn(Builder $inner) => $inner->where('uuid', $farmerUuid),
            ))
            ->with(['farmerProfile.user:id,surname,first_name', 'farmType:id,name', 'community:id,name', 'approvedBy:id,surname'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn(FarmUnit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'farmer' => "{$unit->farmerProfile?->user?->surname} {$unit->farmerProfile?->user?->first_name}",
                'farmer_uuid' => $unit->farmerProfile?->uuid,
                'farm_type' => $unit->farmType?->name,
                'community' => $unit->community?->name,
                'capacity' => $unit->capacity,
                'capacity_unit' => $unit->capacity_unit,
                'is_approved' => $unit->isApproved(),
                'approved_by' => $unit->approvedBy?->surname,
                'can_approve' => $unit->conflictedUserId() !== $user->id,
                'is_active' => $unit->is_active,
            ]);

        return Inertia::render('Admin/FarmUnits/All', [
            'units' => $units,
            'farmers' => $this->farmerOptions($user),
            'filters' => ['farmer' => $farmerUuid],
            ...$this->frame($request, 'farm-units'),
            'permissions' => [
                'create' => $this->access->can($user, 'farm-units.create'),
                'update' => $this->access->can($user, 'farm-units.update'),
                'approve' => $this->access->can($user, 'farm-units.approve'),
            ],
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'farmTypes' => FarmType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeFromList(StoreFarmUnitFromListRequest $request): RedirectResponse
    {
        $farmer = $request->farmer();
        $this->guardFarmer($request->user(), $farmer);

        $data = $request->validated();
        unset($data['farmer_uuid']);

        $farmer->farmUnits()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'The unit is added. It needs to be approved before it counts.');
    }

    public function index(Request $request, FarmerProfile $farmer): Response
    {
        $this->guardFarmer($request->user(), $farmer);

        $farmer->load('user:id,surname,first_name');

        return Inertia::render('Admin/FarmUnits/Index', [
            'farmer' => [
                'id' => $farmer->uuid,
                'name' => "{$farmer->user?->surname} {$farmer->user?->first_name}",
                'community_id' => $farmer->community_id,
            ],
            'units' => $farmer->farmUnits()
                ->with(['farmType:id,name', 'community:id,name', 'approvedBy:id,surname'])
                ->orderBy('name')
                ->get()
                ->map(fn(FarmUnit $unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'farm_type_id' => $unit->farm_type_id,
                    'farm_type' => $unit->farmType?->name,
                    'community_id' => $unit->community_id,
                    'community' => $unit->community?->name,
                    'capacity' => $unit->capacity,
                    'capacity_unit' => $unit->capacity_unit,
                    'is_approved' => $unit->isApproved(),
                    'approved_by' => $unit->approvedBy?->surname,
                    // whoever set it up cannot be the one who says it exists
                    'can_approve' => $unit->conflictedUserId() !== $request->user()->id,
                    'is_active' => $unit->is_active,
                ]),
            ...$this->frame($request, 'farmers'),
            'permissions' => [
                'create' => $this->access->can($request->user(), 'farm-units.create'),
                'update' => $this->access->can($request->user(), 'farm-units.update'),
                'approve' => $this->access->can($request->user(), 'farm-units.approve'),
            ],
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'farmTypes' => FarmType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(FarmUnitRequest $request, FarmerProfile $farmer): RedirectResponse
    {
        $this->guardFarmer($request->user(), $farmer);

        $farmer->farmUnits()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'The unit is added. It needs to be approved before it counts.');
    }

    public function update(FarmUnitRequest $request, FarmerProfile $farmer, FarmUnit $farmUnit): RedirectResponse
    {
        $this->guardFarmer($request->user(), $farmer);
        $this->guardBelongsTo($farmer, $farmUnit);

        $farmUnit->update($request->validated());

        return back()->with('success', 'The unit is saved.');
    }

    public function approve(Request $request, FarmerProfile $farmer, FarmUnit $farmUnit): RedirectResponse
    {
        $this->guardFarmer($request->user(), $farmer);
        $this->guardBelongsTo($farmer, $farmUnit);

        if ($farmUnit->isApproved()) {
            return back()->with('error', 'This unit is already approved.');
        }

        // whoever set the pen up is not the one who says it exists
        if ($farmUnit->conflictedUserId() === $request->user()->id) {
            return back()->with('error', 'Someone other than the person who added this unit needs to approve it.');
        }

        $farmUnit->forceFill([
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ])->save();

        $this->audit->recordOn('farm_unit.approved', $farmUnit);

        return back()->with('success', 'The unit is approved.');
    }

    // the frame and the address the current route group belongs to
    private function frame(Request $request, string $section): array
    {
        $name = $request->route()?->getName() ?? '';
        $group = str_starts_with($name, 'agent.') ? 'agent' : 'admin';

        return [
            'layout' => $group,
            'basePath' => "/{$group}/{$section}",
        ];
    }

    // an agent works their own book, an admin sees the whole platform
    private function reachableFarmerIds(User $user): Builder
    {
        return FarmerProfile::query()
            ->when(! $user->hasRole('admin'), fn(Builder $query) => $query->where('assigned_agent_id', $user->id))
            ->select('id');
    }

    private function farmerOptions(User $user): Collection
    {
        return FarmerProfile::query()
            ->when(! $user->hasRole('admin'), fn(Builder $query) => $query->where('assigned_agent_id', $user->id))
            ->with('user:id,surname,first_name,phone')
            ->get()
            ->map(fn(FarmerProfile $farmer) => [
                'uuid' => $farmer->uuid,
                'name' => "{$farmer->user?->surname} {$farmer->user?->first_name}",
                // an agent often has the number rather than the spelling of the name
                'phone' => $farmer->user?->phone,
                'community_id' => $farmer->community_id,
            ])
            ->sortBy('name')
            ->values();
    }

    // a farmer they do not hold simply is not there, so nothing is learned by guessing
    private function guardFarmer(User $user, FarmerProfile $farmer): void
    {
        abort_if(! $user->hasRole('admin') && $farmer->assigned_agent_id !== $user->id, 404);
    }

    // a unit from another farm is not found here, rather than forbidden
    private function guardBelongsTo(FarmerProfile $farmer, FarmUnit $farmUnit): void
    {
        abort_if($farmUnit->farmer_profile_id !== $farmer->id, 404);
    }
}
