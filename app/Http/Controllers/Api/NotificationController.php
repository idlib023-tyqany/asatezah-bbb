<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Display the authenticated user's paginated notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $notifications = UserNotification::query()
            ->forUser($userId)
            ->with('notification:id,title,body,data')
            ->orderByDesc('created_at')
            ->paginate($this->pageSize($request));

        $data = $notifications->getCollection()->map(static function (UserNotification $userNotification) {
            return [
                'id' => $userNotification->id,
                'title' => $userNotification->notification->title,
                'body' => $userNotification->notification->body,
                'read' => (bool) $userNotification->read,
                'created_at' => $userNotification->created_at?->toIso8601String(),
                'data' => $userNotification->notification->data,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'unread_count' => UserNotification::query()
                ->forUser($userId)
                ->unread()
                ->count(),
        ]);
    }

    /**
     * Return the authenticated user's unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => UserNotification::query()
                ->forUser($request->user()->id)
                ->unread()
                ->count(),
        ]);
    }

    /**
     * Mark one of the authenticated user's notifications as read.
     */
    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($notification) {
            $notification->markAsRead();
        });

        return response()->json(['message' => 'Notification marked as read.']);
    }

    /**
     * Mark all of the authenticated user's notifications as read.
     */
    public function readAll(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            UserNotification::query()
                ->forUser($request->user()->id)
                ->unread()
                ->update([
                    'read' => true,
                    'read_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
