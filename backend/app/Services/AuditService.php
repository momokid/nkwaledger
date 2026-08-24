<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    // roles whose sign-ins are worth keeping; farmers sign in daily and would swamp the table
    private const TRACKED_ROLES = ['admin', 'agent', 'vet', 'adviser', 'supplier'];

    // for things that happen to a record, like an account being disabled
    public function recordOn(string $action, Model $subject, ?array $before = null, ?array $after = null): void
    {
        $this->write($action, $subject::class, $subject->getKey(), $before, $after);
    }

    // for things that touch no record, like a failed login
    public function record(string $action, ?array $details = null): void
    {
        $this->write($action, null, null, null, $details);
    }

    // a farmer's daily sign-in tells us little; a staff sign-in is worth an entry
    public function recordSignIn(User $user): void
    {
        if (! $user->hasAnyRole(self::TRACKED_ROLES)) {
            return;
        }

        $this->write('login.succeeded', $user::class, $user->getKey(), null, null, $user->id);
    }

    private function write(
        string $action,
        ?string $type,
        ?int $id,
        ?array $before,
        ?array $after,
        ?int $userId = null,
    ): void {
        $request = request();

        AuditLog::create([
            'user_id'        => $userId ?? auth()->id(),
            'action'         => $action,
            'auditable_type' => $type,
            'auditable_id'   => $id,
            'old_values'     => $before,
            'new_values'     => $after,
            'ip_address'     => $request?->ip(),
            'user_agent'     => $request?->userAgent(),
        ]);
    }
}
