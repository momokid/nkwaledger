<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
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

    public function sendToPermission(
        string $permission,
        string $kind,
        string $message,
        ?string $link = null,
        ?User $except = null,
    ): void {
        $this->holdersOf($permission)
            ->reject(fn(User $user) => $except !== null && $user->id === $except->id)
            ->each(fn(User $user) => $this->send($user, $kind, $message, $link));
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
