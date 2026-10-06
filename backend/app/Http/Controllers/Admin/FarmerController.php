<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompleteFarmerProfileRequest;
use App\Http\Requests\Admin\RejectionRequest;
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
use App\Services\ForcedLogoutService;
use App\Services\FarmerKycService;
use App\Services\OtpService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Inertia\Inertia;
use Inertia\Response;

class FarmerController extends Controller
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly AuditService $audit,
        private readonly OtpService $otp,
        private readonly FarmerKycService $kyc,
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
                    // an account that is gone has no sessions left to end
                    'has_login' => $profile->user !== null,
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
                // the route is an admin's, so the button is only ever offered to one
                'force_logout' => $user->hasRole('admin') && $this->access->can($user, 'farmers.force-logout'),
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
                'identity_photo_url' => $farmer->identity_photo_path
                    ? route('farmers.identity.photo', $farmer, false)
                    : null,
                'identity_rejected_reason' => $farmer->identity_rejected_reason,
                'registered_by' => $farmer->registeredBy?->surname,
                'is_active' => $farmer->is_active,
            ],
            ...$this->frame($request),
            'permissions' => [
                'update' => $this->access->can($request->user(), 'farmers.update'),
                // an agent submits for their own farmers, so the document form is theirs too
                'capture_identity' => $this->kyc->maySubmitFor($farmer, $request->user()),
                // approving is admin-only, and never for the person who submitted or holds the farmer
                'verify' => $request->user()->hasRole('admin')
                    && $this->access->can($request->user(), 'farmers.verify')
                    && $farmer->conflictedUserId() !== $request->user()->id
                    && $farmer->identity_submitted_by !== $request->user()->id,
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

    // the scope and permission rules live in FarmerKycService, and the request checks them first
    public function storeIdentity(StoreFarmerIdentityRequest $request, FarmerProfile $farmer): RedirectResponse
    {
        $data = $request->validated();

        $this->kyc->submit($farmer, $request->user(), $data['identity_type'], $data['identity_number'], $request->file('photo'));

        return back()->with('success', 'The document is saved. It still needs to be verified.');
    }

    // only the admin route reaches this; the service refuses anyone else and the submitter
    // ends every session and remember-me token this farmer has, on every device
    public function forceLogout(Request $request, FarmerProfile $farmer, ForcedLogoutService $logout): JsonResponse
    {
        $this->guardVisibility($request->user(), $farmer);

        $account = $farmer->user;

        if ($account === null) {
            return response()->json(['message' => HttpResponse::$statusTexts[HttpResponse::HTTP_CONFLICT]], HttpResponse::HTTP_CONFLICT);
        }

        // locking yourself out is never the intent, and with one admin it cannot be undone
        if ($request->user()->is($account)) {
            throw new AccessDeniedHttpException('You cannot change your own account here.');
        }

        // only an account that is a farmer is this route's to act on
        abort_unless($account->hasRole('farmer'), 403);

        $logout->signOutEverywhere($account);

        $this->audit->recordOn('farmer.forced_logout', $farmer);

        return response()->json(['status' => 'signed_out']);
    }

    public function verifyIdentity(Request $request, FarmerProfile $farmer): RedirectResponse
    {
        $this->kyc->approve($farmer, $request->user());

        return back()->with('success', 'The document is verified.');
    }

    // an admin sends a submission back with a reason, the same people rules as approving
    public function rejectIdentity(RejectionRequest $request, FarmerProfile $farmer): RedirectResponse
    {
        $this->kyc->reject($farmer, $request->user(), $request->validated('reason'));

        return back()->with('success', 'ID verification rejected.');
    }

    // private disk, so this is the only way to the file: the agent who holds the farmer, or an admin
    public function identityPhoto(Request $request, FarmerProfile $farmer): \Illuminate\Http\Response
    {
        abort_unless($farmer->identity_photo_path !== null && $this->kyc->mayViewPhotoOf($farmer, $request->user()), 404);

        return response(Storage::disk(config('filesystems.photo_disk'))->get($farmer->identity_photo_path), 200, ['Content-Type' => 'image/webp']);
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

    // the acting user's real role decides the layout, never which URL/route name
    // happened to be hit (Sept 2026 privilege-escalation fix)
    private function frame(Request $request): array
    {
        $group = $request->user()?->hasRole('admin') ? 'admin' : 'agent';

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
