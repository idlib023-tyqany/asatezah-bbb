<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupportController extends Controller
{
    /**
     * List support conversations with pagination, status filters, and search.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString();
        $adminId = $request->user()->id;

        $query = SupportConversation::with([
            'user:id,name,email,avatar',
            'admin:id,name',
            'latestMessage',
        ])->withCount(['messages as unread_messages_count' => function ($query) {
            $query->whereNull('read_at')
                ->whereHas('sender', fn ($senderQuery) => $senderQuery->where('role', '!=', User::ROLE_ADMIN));
        }]);

        // فلترة بحسب الحالة أو التخصيص
        if ($status === 'unassigned') {
            $query->whereNull('admin_id');
        } elseif ($status === 'mine') {
            $query->where('admin_id', $adminId);
        } elseif (in_array($status, ['open', 'closed'], true)) {
            $query->where('status', $status);
        }

        // Search by the user's name or email within the scoped query.
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->whereHas('user', function ($userQuery) use ($q) {
                $userQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $conversations = $query->orderByDesc('updated_at')->paginate($this->pageSize($request));

        return response()->json([
            'data' => $conversations->items(),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Get single conversation details with all messages.
     */
    public function show(SupportConversation $conversation): JsonResponse
    {
        // تحميل العلاقات مع ترتيب الرسائل وتجنب استهلاك الذاكرة المفرط
        $conversation->load([
            'user:id,name,email,avatar',
            'admin:id,name',
            'messages' => fn ($q) => $q->orderBy('created_at', 'asc'),
        ]);

        return response()->json(['data' => $conversation]);
    }

    /**
     * Claim (assign admin to) a conversation safely.
     */
    public function claim(Request $request, SupportConversation $conversation): JsonResponse
    {
        $currentAdmin = $request->user();

        // منع الاستحواذ إذا كانت مسندة بالفعل لأدمن آخر
        if ($conversation->admin_id && $conversation->admin_id !== $currentAdmin->id) {
            $assignedAdminName = $conversation->admin?->name ?? 'another admin';

            return response()->json([
                'message' => "This conversation is already claimed by {$assignedAdminName}.",
            ], 422);
        }

        try {
            DB::transaction(function () use ($conversation, $currentAdmin) {
                $conversation->update([
                    'admin_id' => $currentAdmin->id,
                    'status' => 'open',
                ]);
            });

            return response()->json([
                'message' => 'Conversation claimed successfully.',
                'data' => $conversation->fresh(['user', 'admin']),
            ]);
        } catch (Throwable $e) {
            Log::error("SupportController: Failed to claim conversation ID {$conversation->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to claim conversation.',
            ], 500);
        }
    }

    /**
     * Close a support conversation.
     */
    public function close(SupportConversation $conversation): JsonResponse
    {
        try {
            DB::transaction(function () use ($conversation) {
                $conversation->update([
                    'status' => 'closed',
                ]);
            });

            return response()->json(['message' => 'Conversation marked as closed.']);
        } catch (Throwable $e) {
            Log::error("SupportController: Failed to close conversation ID {$conversation->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to close conversation.',
            ], 500);
        }
    }

    /**
     * Re-assign or unclaim conversation.
     */
    public function unclaim(SupportConversation $conversation): JsonResponse
    {
        try {
            DB::transaction(function () use ($conversation) {
                $conversation->update([
                    'admin_id' => null,
                ]);
            });

            return response()->json(['message' => 'Conversation released to general queue.']);
        } catch (Throwable $e) {
            Log::error("SupportController: Failed to unclaim conversation ID {$conversation->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to release conversation.',
            ], 500);
        }
    }
}
