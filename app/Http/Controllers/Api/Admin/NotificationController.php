<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationDeliveryService $service) {}

    /**
     * List all notifications with pagination and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::query()->orderByDesc('created_at');

        // فلترة بالبحث النصي الشامل
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('title', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%");
            });
        }

        $notifications = $query->paginate($this->pageSize($request));

        return response()->json([
            'status' => 'success',
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Send a notification to all users.
     */
    public function sendToAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'data' => ['nullable', 'array'],
        ]);

        try {
            $notification = $this->service->toAll(
                $validated['title'],
                $validated['body'],
                $validated['data'] ?? []
            );

            return response()->json([
                'message' => 'Notification sent successfully to all users.',
                'data' => $notification,
            ], 201);
        } catch (Throwable $e) {
            Log::error("NotificationController: Failed to send notification to all. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to send notification.',
            ], 500);
        }
    }

    /**
     * Send a notification to users matching a specialization.
     */
    public function sendToSpecialization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'specialization' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'data' => ['nullable', 'array'],
        ]);

        try {
            $notification = $this->service->toSpecialization(
                $validated['specialization'],
                $validated['title'],
                $validated['body'],
                $validated['data'] ?? []
            );

            return response()->json([
                'message' => "Notification sent successfully to {$validated['specialization']} users.",
                'data' => $notification,
            ], 201);
        } catch (Throwable $e) {
            Log::error("NotificationController: Failed to send notification to specialization: {$validated['specialization']}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to send notification.',
            ], 500);
        }
    }

    /**
     * Send a notification to a specific single user.
     */
    public function sendToUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'data' => ['nullable', 'array'],
        ]);

        try {
            $notification = $this->service->toUser(
                $validated['user_id'],
                $validated['title'],
                $validated['body'],
                $validated['data'] ?? []
            );

            return response()->json([
                'message' => 'Notification sent successfully to the user.',
                'data' => $notification,
            ], 201);
        } catch (Throwable $e) {
            Log::error("NotificationController: Failed to send notification to user ID {$validated['user_id']}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to send notification.',
            ], 500);
        }
    }

    /**
     * Delete a single notification record.
     */
    public function destroy(Notification $notification): JsonResponse
    {
        DB::transaction(function () use ($notification) {
            $notification->delete();
        });

        return response()->json(['message' => 'Notification history record deleted.']);
    }

    /**
     * ميزة جديدة: الحذف الجماعي لإسجلات الإشعارات (Bulk Delete)
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:notifications,id'],
        ]);

        DB::transaction(function () use ($validated) {
            Notification::whereIn('id', $validated['ids'])->delete();
        });

        return response()->json(['message' => 'Selected notifications deleted successfully.']);
    }

    /**
     * ميزة جديدة: إحصائيات الإشعارات (Notifications Statistics)
     */
    public function statistics(): JsonResponse
    {
        $totalNotifications = Notification::count();
        $todayNotifications = Notification::whereDate('created_at', today())->count();

        return response()->json([
            'total_notifications' => $totalNotifications,
            'today_notifications' => $todayNotifications,
        ]);
    }
}
