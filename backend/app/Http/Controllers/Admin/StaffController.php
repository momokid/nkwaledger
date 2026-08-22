<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\OtpService;
use App\Services\StaffInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(
        private readonly StaffInvitationService $invitations,
        private readonly AccessControlService $access,
        private readonly OtpService $otpService,
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

        return back()->with('success', "A fresh code is on its way to {$user->first_name}.");
    }
}
