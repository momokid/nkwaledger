<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn(Notification $note) => [
                'uuid' => $note->uuid,
                'kind' => $note->kind,
                'message' => $note->message,
                'link' => $note->link,
                'is_read' => $note->isRead(),
                'created_at' => $note->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $notifications]);
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 404);

        $notification->markRead();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
