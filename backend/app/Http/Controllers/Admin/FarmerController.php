<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompleteFarmerProfileRequest;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as SupportCollection;
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
                ->with(['user:id,surname,first_name,phone,phone_verified_at', 'community:id,name', 'assignedAgent:id,surname,first_name'])
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn(FarmerProfile $profile) => [
                    // the browser only ever sees the uuid
                    'id' => $profile->uuid,
                    'name' => "{$profile->user?->surname} {$profile->user?->first_name}",
                    'phone' => $profile->user?->phone,
                    'phone_verified' => $profile->user?->phone_verified_at !== null,
                    'community' => $profile->community?->name,
                    'agent' => $profile->assignedAgent
                        ? "{$profile->assignedAgent->surname} {$profile->assignedAgent->first_name}"
                        : null,
                    'identity_verified' => $profile->identity_verified_at !== null,
                    'is_active' => $profile->is_active,
                ]),
            'pending' => $this->pendingFarmers(),
            ...$this->frame($request),
            'permissions' => [
                'create' => $this->access->can($user, 'farmers.create'),
                'update' => $this->access->can($user, 'farmers.update'),
                'verify' => $this->access->can($user, 'farmers.verify'),
                'assign' => $user->hasRole('admin'),
            ],
            'agents' => $this->agentOptions($user),
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'farmerGroups' => FarmerGroup::where('is_active', true)->orderBy('name')->get(['id', 'name', 'community_id']),
            'farmTypes' => FarmType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, FarmerProfile $farmer): Response
    {
        $this->guardVisibility($request->user(), $farmer);

        $farmer->load([
            'user:id,surname,first_name,other_name,phone,phone_verified_at,is_active',
            'community:id,name',
            'farmerGroup:id,name',
            'farmTypes:id,name',
            'registeredBy:id,surname,first_name',
            'assignedAgent:id,surname,first_name',
            'identityVerifiedBy:id,surname,first_name',
        ]);

        return Inertia::render('Admin/Farmers/Show', [
            'farmer' => [
                'id' => $farmer->uuid,
                'name' => "{$farmer->user?->surname} {$farmer->user?->first_name}",
                'phone' => $farmer->user?->phone,
                'phone_verified' => $farmer->user?->phone_verified_at !== null,
                // the page only offers a resend when the last code has run out
                'has_live_code' => $farmer->user
                    ? $this->otp->hasLiveCode($farmer->user->phone, 'invitation')
                    : false,
                'gender' => $farmer->gender,
                'date_of_birth' => $farmer->date_of_birth,
                'home_address' => $farmer->home_address,
                'community_id' => $farmer->community_id,
                'community' => $farmer->community?->name,
                'farmer_group_id' => $farmer->farmer_group_id,
                'assigned_agent_id' => $farmer->assigned_agent_id,
                'agent' => $farmer->assignedAgent
                    ? "{$farmer->assignedAgent->surname} {$farmer->assignedAgent->first_name}"
                    : null,
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
            ...$this->frame($request),
            'permissions' => [
                'update' => $this->access->can($request->user(), 'farmers.update'),
                'verify' => $this->access->can($request->user(), 'farmers.verify')
                    && $farmer->conflictedUserId() !== $request->user()->id,
                'assign' => $request->user()->hasRole('admin'),
            ],
            'agents' => $this->agentOptions($request->user()),
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'farmerGroups' => FarmerGroup::where('is_active', true)->orderBy('name')->get(['id', 'name', 'community_id']),
            'farmTypes' => FarmType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreFarmerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        $user = DB::transaction(function () use ($data, $actor) {
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
                'home_address' => $data['home_address'] ?? null,
                'community_id' => $data['community_id'],
                'farmer_group_id' => $data['farmer_group_id'] ?? null,
                'registered_by' => $actor->id,
                'assigned_agent_id' => $this->agentFor($actor, $data['assigned_agent_id'] ?? null),
                'onboarded_at' => now(),
            ]);

            $profile->farmTypes()->sync($data['farm_type_ids']);

            return $user;
        });

        // sent only once the rows are safely committed, so a rollback never costs an SMS
        // the same invitation staff get, since it carries the link and lets them set a password
        $this->otp->generate($user->phone, 'invitation');

        return back()->with('success', "{$user->first_name} is registered. We sent them a code and a link to set their password.");
    }

    public function complete(Request $request, int $user): Response
    {
        $account = $this->pendingAccount($user);

        return Inertia::render('Admin/Farmers/Complete', [
            'account' => [
                'id' => $account->id,
                'surname' => $account->surname,
                'first_name' => $account->first_name,
                'other_name' => $account->other_name,
                'phone' => $account->phone,
                'phone_verified' => $account->phone_verified_at !== null,
            ],
            ...$this->frame($request),
            'permissions' => [
                'assign' => $request->user()->hasRole('admin'),
            ],
            'agents' => $this->agentOptions($request->user()),
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'farmerGroups' => FarmerGroup::where('is_active', true)->orderBy('name')->get(['id', 'name', 'community_id']),
            'farmTypes' => FarmType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeComplete(CompleteFarmerProfileRequest $request, int $user): RedirectResponse
    {
        $account = $this->pendingAccount($user);

        $data = $request->validated();
        $actor = $request->user();

        DB::transaction(function () use ($account, $data, $actor) {
            $profile = FarmerProfile::create([
                'user_id' => $account->id,
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'home_address' => $data['home_address'] ?? null,
                'community_id' => $data['community_id'],
                'farmer_group_id' => $data['farmer_group_id'] ?? null,
                'registered_by' => $actor->id,
                'assigned_agent_id' => $this->agentFor($actor, $data['assigned_agent_id'] ?? null),
                'onboarded_at' => now(),
            ]);

            $profile->farmTypes()->sync($data['farm_type_ids']);
        });

        return redirect($this->frame($request)['basePath'])
            ->with('success', "{$account->first_name}'s profile is complete.");
    }

    public function resendActivation(Request $request, FarmerProfile $farmer): RedirectResponse
    {
        $this->guardVisibility($request->user(), $farmer);

        if ($farmer->user?->phone_verified_at !== null) {
            throw ValidationException::withMessages([
                'resend' => 'This farmer has already confirmed their number.',
            ]);
        }

        // each message costs money, so a code that still works is left alone
        if ($this->otp->hasLiveCode($farmer->user->phone, 'invitation')) {
            return back()->with('success', 'They still have a code that works. Ask them to check their messages.');
        }

        $this->otp->generate($farmer->user->phone, 'invitation');

        return back()->with('success', 'A fresh code is on its way.');
    }

    public function update(UpdateFarmerRequest $request, FarmerProfile $farmer): RedirectResponse
    {
        $this->guardVisibility($request->user(), $farmer);

        $data = $request->validated();

        $farmer->update([
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'home_address' => $data['home_address'] ?? null,
            'community_id' => $data['community_id'],
            'farmer_group_id' => $data['farmer_group_id'] ?? null,
            // only an admin moves a farmer between agents, so anyone else keeps the current holder
            'assigned_agent_id' => $request->user()->hasRole('admin')
                ? ($data['assigned_agent_id'] ?? null)
                : $farmer->assigned_agent_id,
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

        // whoever serves this farmer cannot also vouch for their document
        if ($farmer->conflictedUserId() === $request->user()->id) {
            throw ValidationException::withMessages([
                'identity_number' => 'Someone other than the person who holds this farmer needs to verify the document.',
            ]);
        }

        $farmer->forceFill([
            'identity_verified_at' => now(),
            'identity_verified_by' => $request->user()->id,
        ])->save();

        $this->audit->recordOn('farmer.identity_verified', $farmer);

        return back()->with('success', 'The document is verified.');
    }

    // a farmer account with no profile, which is what signing up on its own leaves behind
    private function pendingAccount(int $id): User
    {
        return User::role('farmer')
            ->whereDoesntHave('farmerProfile')
            ->whereKey($id)
            ->firstOrFail();
    }

    // nobody holds these yet, so every user who may register sees the whole list
    private function pendingFarmers(): SupportCollection
    {
        return User::role('farmer')
            ->whereDoesntHave('farmerProfile')
            ->orderBy('surname')
            ->get(['id', 'surname', 'first_name', 'phone', 'phone_verified_at'])
            ->map(fn(User $user) => [
                'id' => $user->id,
                'name' => "{$user->surname} {$user->first_name}",
                'phone' => $user->phone,
                'phone_verified' => $user->phone_verified_at !== null,
            ]);
    }

    // the frame and the address the current route group belongs to, so one page serves both
    private function frame(Request $request): array
    {
        $name = $request->route()?->getName() ?? '';
        $group = str_starts_with($name, 'agent.') ? 'agent' : 'admin';

        return [
            'layout' => $group,
            'basePath' => "/{$group}/farmers",
        ];
    }

    // an agent sees the farmers they hold; an admin sees the whole book
    private function visibleTo(User $user): Builder
    {
        return FarmerProfile::query()
            ->when(! $user->hasRole('admin'), fn(Builder $query) => $query->where('assigned_agent_id', $user->id));
    }

    // a farmer they do not hold simply is not there, so nothing is learned by guessing
    private function guardVisibility(User $user, FarmerProfile $farmer): void
    {
        abort_if(! $user->hasRole('admin') && $farmer->assigned_agent_id !== $user->id, 404);
    }

    // an agent keeps the farmers they bring in, so the posted value is ignored for them
    private function agentFor(User $actor, ?int $chosen): ?int
    {
        return $actor->hasRole('admin') ? $chosen : $actor->id;
    }

    private function agentOptions(User $user): Collection
    {
        if (! $user->hasRole('admin')) {
            return new Collection();
        }

        return User::role('agent')
            ->where('is_active', true)
            ->orderBy('surname')
            ->get(['id', 'surname', 'first_name']);
    }
}
