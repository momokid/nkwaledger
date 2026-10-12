<?php

namespace App\Policies;

use App\Models\FarmerProfile;
use App\Models\PendingEntry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// an agent's entry is controlled by the farmer's CURRENT agent (not necessarily the one who recorded it) and
// answered by the farmer. An admin only looks. Anyone outside that reach gets a 404, as if it did not exist
class PendingEntryPolicy
{
    // record a loan, or open the form for it
    public function record(User $user, FarmerProfile $farmer): Response|bool
    {
        if (! $user->hasRole('agent')) {
            return false;
        }

        return (int) $farmer->assigned_agent_id === $user->id ? true : Response::denyAsNotFound();
    }

    // send a code, enter a code, withdraw
    public function manage(User $user, PendingEntry $entry): Response|bool
    {
        if (! $user->hasRole('agent')) {
            return false;
        }

        return (int) $entry->farmerProfile->assigned_agent_id === $user->id ? true : Response::denyAsNotFound();
    }

    // accept or decline in the app
    public function respond(User $user, PendingEntry $entry): Response|bool
    {
        if (! $user->hasRole('farmer')) {
            return false;
        }

        return (int) $entry->farmerProfile->user_id === $user->id ? true : Response::denyAsNotFound();
    }

    public function view(User $user, PendingEntry $entry): Response|bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $farmer = $entry->farmerProfile;

        $mine = ($user->hasRole('farmer') && (int) $farmer->user_id === $user->id)
            || ($user->hasRole('agent') && (int) $farmer->assigned_agent_id === $user->id);

        return $mine ? true : Response::denyAsNotFound();
    }
}
