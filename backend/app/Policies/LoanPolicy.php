<?php

namespace App\Policies;

use App\Models\FarmerProfile;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// who may see and enter loans. A farmer sees and enters only their own; an admin reads them and enters
// none; agents, vets and advisers have no loan rights yet. A loan outside someone's reach does not exist
// for them: 404, never 403
class LoanPolicy
{
    // the farmer's own list
    public function viewAny(User $user): bool
    {
        return $user->hasRole('farmer');
    }

    public function view(User $user, Loan $loan): Response|bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('farmer') && (int) $loan->farmerProfile?->user_id === $user->id) {
            return true;
        }

        return Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('farmer');
    }

    // the section on a farmer's page: an admin reads it, and the farmer's current agent reads it and acts on it
    public function viewForFarmer(User $user, FarmerProfile $farmer): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('agent') && (int) $farmer->assigned_agent_id === $user->id);
    }
}
