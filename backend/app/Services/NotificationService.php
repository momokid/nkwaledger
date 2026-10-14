<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;

class NotificationService
{
    public function __construct(private readonly AccessControlService $access) {}

    public function send(User $user, string $kind, string $message, ?string $link = null): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'kind' => $kind,
            'message' => $message,
            'link' => $link,
        ]);
    }

    // $link is a single literal string sent to every holder alike - only ever safe
    // when every holder of $permission reaches the same page through the same URL.
    // A permission held by more than one role (e.g. admin AND agent, each with their
    // own differently-prefixed page for it) must use $linkFor instead, so each
    // recipient's own link is resolved from their own role rather than one hardcoded
    // for whichever role the original author had in mind (Sept 2026
    // privilege-escalation fix - this is what let an agent's notification carry an
    // admin-only URL)
    public function sendToPermission(
        string $permission,
        string $kind,
        string $message,
        ?string $link = null,
        ?User $except = null,
        ?Closure $linkFor = null,
    ): void {
        $this->holdersOf($permission)
            ->reject(fn(User $user) => $except !== null && $user->id === $except->id)
            ->each(fn(User $user) => $this->send($user, $kind, $message, $linkFor !== null ? $linkFor($user) : $link));
    }

    public function unreadCountFor(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        return Notification::query()->where('user_id', $user->id)->unread()->count();
    }

    private function holdersOf(string $permission): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn(User $user) => $this->access->can($user, $permission));
    }
}
