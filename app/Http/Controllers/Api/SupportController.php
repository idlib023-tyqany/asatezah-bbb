<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Http\Resources\SupportConversationResource;
use App\Models\Message;
use App\Models\SupportConversation;
use App\Support\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    /**
     * List the authenticated user's or admin's support conversations (Paginated).
     */
    public function inbox(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');

        $query = $isAdmin
            ? SupportConversation::query()
            : SupportConversation::where('user_id', $user->id);

        $conversations = $query
            ->with(['user:id,name,avatar,role', 'latestMessage'])
            ->orderByDesc('updated_at')
            ->paginate($this->pageSize($request, 15));

        return SupportConversationResource::collection($conversations);
    }

    /**
     * Start a new support conversation with an initial optional message.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $user = $request->user();

        $conversation = DB::transaction(function () use ($request, $user, $validated) {
            $conv = SupportConversation::create([
                'user_id' => $user->id,
                'subject' => $validated['subject'],
                'status' => SupportConversation::STATUS_OPEN ?? 'open',
            ]);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = FileStorage::store($request->file('image'), 'support-images');
            }

            if (! empty($validated['message']) || $imagePath !== null) {
                $conv->messages()->create([
                    'sender_id' => $user->id,
                    'body' => $validated['message'] ?? null,
                    'image_path' => $imagePath,
                ]);
            }

            return $conv;
        });

        return response()->json([
            'message' => 'Support ticket created successfully.',
            'data' => new SupportConversationResource($conversation->load(['user', 'latestMessage'])),
        ], 201);
    }

    /**
     * List the paginated messages of a conversation.
     */
    public function messages(Request $request, SupportConversation $conversation): AnonymousResourceCollection
    {
        $this->authorizeConversation($request->user(), $conversation);

        $messages = $conversation->messages()
            ->with('sender:id,name,avatar,role')
            ->latest() // ترتيب تنازلي يناسب شات التطبيقات
            ->paginate($this->pageSize($request, 25));

        return MessageResource::collection($messages);
    }

    /**
     * Send a message (text, image, or both) within an open conversation.
     */
    public function send(Request $request, SupportConversation $conversation): MessageResource
    {
        $this->authorizeConversation($request->user(), $conversation);

        // التحقق من أن التذكرة ليست مغلقة
        $closedStatus = SupportConversation::STATUS_CLOSED ?? 'closed';
        if ($conversation->status === $closedStatus) {
            abort(422, 'Cannot reply to a closed conversation. Please open a new ticket.');
        }

        $validated = $request->validate([
            'body' => ['required_without:image', 'nullable', 'string', 'max:5000'],
            'image' => ['required_without:body', 'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');

        $message = DB::transaction(function () use ($request, $conversation, $user, $validated, $isAdmin) {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = FileStorage::store($request->file('image'), 'support-images');
            }

            $msg = $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => $validated['body'] ?? null,
                'image_path' => $imagePath,
            ]);

            // تحديث حالة التذكرة بناءً على من قام بالرد
            $conversation->update([
                'status' => $isAdmin ? SupportConversation::STATUS_PENDING : SupportConversation::STATUS_OPEN,
                'last_message_at' => now(),
            ]);

            return $msg;
        });

        return new MessageResource($message->load('sender:id,name,avatar,role'));
    }

    /**
     * Close a support conversation (Admin only or Ticket Creator).
     */
    public function close(Request $request, SupportConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $closedStatus = SupportConversation::STATUS_CLOSED ?? 'closed';

        DB::transaction(function () use ($conversation, $closedStatus) {
            $conversation->update(['status' => $closedStatus]);
        });

        return response()->json([
            'message' => 'Conversation marked as closed.',
            'status' => $closedStatus,
        ]);
    }

    /**
     * Check authorization for managing conversation.
     */
    private function authorizeConversation($user, SupportConversation $conversation): void
    {
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');

        if (! $isAdmin && $conversation->user_id !== $user->id) {
            abort(403, 'You do not have permission to access this conversation.');
        }
    }
}
