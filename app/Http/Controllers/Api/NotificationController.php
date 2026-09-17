<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    // GET /api/notifications
    public function index(): JsonResponse
    {
        $notifications = Notification::where('owner_id', auth('api')->id())
            ->orderByDesc('created_at')
            ->get();

        return response()->json(
            $notifications->map(fn($n) => $n->toApiArray())
        );
    }

    // PUT /api/notifications/{id}/read
    public function markRead(string $id): JsonResponse
    {
        Notification::where('id', $id)
            ->where('owner_id', auth('api')->id())
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    // PUT /api/notifications/read-all
    public function markAllRead(): JsonResponse
    {
        Notification::where('owner_id', auth('api')->id())
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }
}
