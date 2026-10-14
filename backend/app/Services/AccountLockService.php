<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// blocks or frees a farmer's own sign-in; what the block does lives in the auth guard
class AccountLockService
{
    public function __construct(
        private readonly ForcedLogoutService $logout,
        private readonly NotificationService $notifications,
    ) {}

    public function lock(FarmerProfile $farmer, User $account, User $by): void
    {
        DB::transaction(function () use ($account, $by) {
            $account->forceFill(['locked_at' => now(), 'locked_by' => $by->id])->save();

            $this->logout->signOutEverywhere($account);
        });

        $this->tell($farmer, $account, $by, 'farmer.locked', ' has been locked. They cannot sign in until an admin unlocks the account.');
    }

    public function unlock(FarmerProfile $farmer, User $account, User $by): void
    {
        $account->forceFill(['locked_at' => null, 'locked_by' => null])->save();

        $this->tell($farmer, $account, $by, 'farmer.unlocked', ' has been unlocked and can sign in again.');
    }

    // the farmer's own agent and every other admin; never the admin who did it
    private function tell(FarmerProfile $farmer, User $account, User $by, string $kind, string $ending): void
    {
        $message = "{$account->surname} {$account->first_name}{$ending}";

        User::query()
            ->where('is_active', true)
            ->where('id', '!=', $by->id)
            ->where(fn($query) => $query->role('admin')->orWhere('id', $farmer->assigned_agent_id))
            ->get()
            ->each(fn(User $user) => $this->notifications->send(
                $user,
                $kind,
                $message,
                $user->hasRole('admin') ? '/admin/farmers' : '/agent/farmers',
            ));
    }
}
