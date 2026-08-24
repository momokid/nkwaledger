<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditService;
use App\Services\OtpService;
use App\Services\StaffInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StaffController extends Controller
{
    public function __construct(
        private readonly StaffInvitationService $invitations,
        private readonly AccessControlService $access,
        private readonly OtpService $otpService,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Staff/Index', [
            'staff' => User::query()
                ->role(StaffInvitationService::INVITABLE_ROLES)
                ->with('roles:id,name')
                ->orderBy('surname')
                ->paginate(15)
                ->through(fn(User $staff) => [
                    'id'           => $staff->id,
                    'surname'      => $staff->surname,
                    'first_name'   => $staff->first_name,
                    'other_name'   => $staff->other_name,
                    'phone'        => $staff->phone,
                    'email'        => $staff->email,
                    'role'         => $staff->roles->first()?->name,
                    'is_active'    => $staff->is_active,
                    // derived rather than shipped, so the password never leaves the server
                    'is_activated' => $staff->password !== null,
                    'invited_at'   => $staff->created_at,
                ]),
            'roles' => StaffInvitationService::INVITABLE_ROLES,
            'permissions' => [
                'create' => $this->access->can($user, 'staff.create'),
                'update' => $this->access->can($user, 'staff.update'),
                'delete' => $this->access->can($user, 'staff.delete'),
            ],
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $staff = $this->invitations->invite($request->validated());

        return back()->with('success', "Invitation sent to {$staff->first_name}. They have an hour to activate the account.");
    }

    public function resend(User $user): RedirectResponse
    {
        // an account that already has a password has nothing left to activate
        if ($user->password !== null) {
            throw ValidationException::withMessages([
                'resend' => "{$user->first_name} has already activated their account.",
            ]);
        }

        $this->otpService->generate($user->phone, 'invitation');

        $this->audit->recordOn('staff.invitation_resent', $user);

        return back()->with('success', "A fresh code is on its way to {$user->first_name}.");
    }

    public function disable(Request $request, User $user): RedirectResponse
    {
        $this->guardSelf($request, $user);

        $user->update(['is_active' => false]);

        $this->audit->recordOn('staff.disabled', $user);

        return back()->with('success', "{$user->first_name} can no longer sign in.");
    }

    public function enable(Request $request, User $user): RedirectResponse
    {
        $this->guardSelf($request, $user);

        $user->update(['is_active' => true]);

        $this->audit->recordOn('staff.enabled', $user);

        return back()->with('success', "{$user->first_name} can sign in again.");
    }

    public function destroy(User $user): RedirectResponse
    {
        // once someone has activated, their name may sit against records that must not lose their owner
        if ($user->password !== null) {
            throw ValidationException::withMessages([
                'destroy' => "{$user->first_name} has already activated. Disable the account instead of cancelling it.",
            ]);
        }

        $name = $user->first_name;

        DB::transaction(function () use ($user) {
            // the account is about to go, so the entry has to hold what was there
            $this->audit->recordOn('staff.invitation_cancelled', $user, [
                'phone'      => $user->phone,
                'surname'    => $user->surname,
                'first_name' => $user->first_name,
                'role'       => $user->roles->first()?->name,
            ]);

            // the invitation code outlives the account unless it goes too
            OtpCode::where('identifier', $user->phone)->where('type', 'invitation')->delete();

            $user->delete();
        });

        return back()->with('success', "The invitation to {$name} has been cancelled.");
    }

    // locking yourself out is never the intent, and with one admin it cannot be undone
    private function guardSelf(Request $request, User $user): void
    {
        if ($request->user()->is($user)) {
            throw new AccessDeniedHttpException('You cannot change your own account here.');
        }
    }
}
