<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFarmerIdentityRequest;
use App\Http\Requests\Admin\StoreFarmerRequest;
use App\Http\Requests\Admin\UpdateFarmerRequest;
use App\Models\Community;
use App\Models\FarmerGroup;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditService;
use App\Services\OtpService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FarmerController extends Controller
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly AuditService $audit,
        private readonly OtpService $otp,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Farmers/Index', [
            'farmers' => $this->visibleTo($user)
                ->with(['user:id,surname,first_name,phone,phone_verified_at', 'community:id,name'])
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn(FarmerProfile $profile) => [
                    'id' => $profile->id,
                    'name' => "{$profile->user?->surname} {$profile->user?->first_name}",
                    'phone' => $profile->user?->phone,
                    'phone_verified' => $profile->user?->phone_verified_at !== null,
                    'community' => $profile->community?->name,
                    'identity_verified' => $profile->identity_verified_at !== null,
                    'is_active' => $profile->is_active,
                ]),
            'permissions' => [
                'create' => $this->access->can($user, 'farmers.create'),
                'update' => $this->access->can($user, 'farmers.update'),
                'verify' => $this->access->can($user, 'farmers.verify'),
            ],
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'farmerGroups' => FarmerGroup::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'farmTypes' => FarmType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, FarmerProfile $farmer): Response
    {
        $this->guardVisibility($request->user(), $farmer);

        $farmer->load(['user:id,surname,first_name,other_name,phone,phone_verified_at,is_active', 'community:id,name', 'farmerGroup:id,name', 'farmTypes:id,name', 'registeredBy:id,surname,first_name', 'identityVerifiedBy:id,surname,first_name']);

        return Inertia::render('Admin/Farmers/Show', [
            'farmer' => [
                'id' => $farmer->id,
                'name' => "{$farmer->user?->surname} {$farmer->user?->first_name}",
                'phone' => $farmer->user?->phone,
                'phone_verified' => $farmer->user?->phone_verified_at !== null,
                'gender' => $farmer->gender,
                'date_of_birth' => $farmer->date_of_birth,
                'community_id' => $farmer->community_id,
                'community' => $farmer->community?->name,
                'farmer_group_id' => $farmer->farmer_group_id,
                'farm_type_ids' => $farmer->farmTypes->pluck('id'),
                'farm_types' => $farmer->farmTypes->pluck('name'),
                'identity_type' => $farmer->identity_type?->value,
                'identity_type_label' => $farmer->identity_type?->label(),
                'has_identity' => $farmer->identity_number_hash !== null,
                'identity_verified_at' => $farmer->identity_verified_at,
                'identity_verified_by' => $farmer->identityVerifiedBy?->surname,
                'registered_by' => $farmer->registeredBy?->surname,
                'is_active' => $farmer->is_active,
            ],
            'permissions' => [
                'update' => $this->access->can($request->user(), 'farmers.update'),
                'verify' => $this->access->can($request->user(), 'farmers.verify'),
            ],
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'farmerGroups' => FarmerGroup::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'farmTypes' => FarmType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreFarmerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'surname' => $data['surname'],
                'first_name' => $data['first_name'],
                'other_name' => $data['other_name'] ?? null,
                'phone' => $data['phone'],
                'password' => null,
            ]);

            $user->assignRole('farmer');

            $profile = FarmerProfile::create([
                'user_id' => $user->id,
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'community_id' => $data['community_id'],
                'farmer_group_id' => $data['farmer_group_id'] ?? null,
                'registered_by' => $request->user()->id,
                'onboarded_at' => now(),
            ]);

            $profile->farmTypes()->sync($data['farm_type_ids']);

            return $user;
        });

        // sent only once the rows are safely committed, so a rollback never costs an SMS
        $this->otp->generate($user->phone, 'phone_verification');

        return back()->with('success', "{$user->first_name} is registered. We sent a code to their phone to confirm the number.");
    }

    public function update(UpdateFarmerRequest $request, FarmerProfile $farmer): RedirectResponse
    {
        $this->guardVisibility($request->user(), $farmer);

        $data = $request->validated();

        $farmer->update([
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'community_id' => $data['community_id'],
            'farmer_group_id' => $data['farmer_group_id'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        $farmer->farmTypes()->sync($data['farm_type_ids']);

        return back()->with('success', 'The farmer details are saved.');
    }

    public function storeIdentity(StoreFarmerIdentityRequest $request, FarmerProfile $farmer): RedirectResponse
    {
        $this->guardVisibility($request->user(), $farmer);

        $data = $request->validated();

        // capturing is not verifying, so any earlier verification is cleared with the document
        $farmer->forceFill([
            'identity_type' => $data['identity_type'],
            'identity_number' => $data['identity_number'],
            'identity_verified_at' => null,
            'identity_verified_by' => null,
        ])->save();

        $this->audit->recordOn('farmer.identity_captured', $farmer);

        return back()->with('success', 'The document is saved. It still needs to be verified.');
    }

    public function verifyIdentity(Request $request, FarmerProfile $farmer): RedirectResponse
    {
        if ($farmer->identity_number_hash === null) {
            throw ValidationException::withMessages([
                'identity_number' => 'There is no document on this account yet. Please capture one first.',
            ]);
        }

        // the person who registered a farmer cannot also vouch for their document
        if ($farmer->registered_by === $request->user()->id) {
            throw ValidationException::withMessages([
                'identity_number' => 'Someone other than the person who registered this farmer needs to verify the document.',
            ]);
        }

        $farmer->forceFill([
            'identity_verified_at' => now(),
            'identity_verified_by' => $request->user()->id,
        ])->save();

        $this->audit->recordOn('farmer.identity_verified', $farmer);

        return back()->with('success', 'The document is verified.');
    }

    // an agent sees only the farmers they brought in; an admin sees the whole book
    private function visibleTo(User $user): Builder
    {
        return FarmerProfile::query()
            ->when(! $user->hasRole('admin'), fn(Builder $query) => $query->where('registered_by', $user->id));
    }

    private function guardVisibility(User $user, FarmerProfile $farmer): void
    {
        abort_if(! $user->hasRole('admin') && $farmer->registered_by !== $user->id, 403);
    }
}
