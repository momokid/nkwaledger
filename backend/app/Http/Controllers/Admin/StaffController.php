<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Services\StaffInvitationService;
use Illuminate\Http\RedirectResponse;

class StaffController extends Controller
{
    public function __construct(private readonly StaffInvitationService $invitations) {}

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $staff = $this->invitations->invite($request->validated());

        return back()->with('success', "Invitation sent to {$staff->first_name}. They have an hour to activate the account.");
    }
}
