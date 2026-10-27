<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommentController extends Controller
{
    /**
     * List paginated comments for a published file.
     */
    public function index(Request $request, File $file): AnonymousResourceCollection
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        $comments = $file->comments()
            ->with('user:id,name,avatar,role') // جلب الحقول المطلوبة فقط لرفع الأداء
            ->latest()
            ->paginate($this->pageSize($request, 15));

        return CommentResource::collection($comments);
    }

    /**
     * Store a new comment on a published file.
     */
    public function store(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        $user = $request->user();

        // حظر التعليق إذا كان الحساب غير مصرح له بالمساهمة
        if (isset($user->can_contribute) && ! $user->can_contribute) {
            abort(403, 'Your account is currently restricted from commenting.');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        try {
            // إرجاع التعليق مباشرة من المعاملة لتجنب أي مشاكل في المرجع
            $comment = DB::transaction(function () use ($file, $user, $validated) {
                return $file->comments()->create([
                    'user_id' => $user->id,
                    'body' => $validated['body'],
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully.',
                'data' => new CommentResource($comment->load('user:id,name,avatar,role')),
            ], 201);
        } catch (Throwable $e) {
            Log::error("CommentController: Failed to store comment for file ID {$file->id}. Error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment.',
            ], 500);
        }
    }

    /**
     * Update (edit) the authenticated user's own comment.
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorizeCommentOwner($request->user(), $comment);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($comment, $validated) {
                $comment->update(['body' => $validated['body']]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully.',
                'data' => new CommentResource($comment->load('user:id,name,avatar,role')),
            ]);
        } catch (Throwable $e) {
            Log::error("CommentController: Failed to update comment ID {$comment->id}. Error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment.',
            ], 500);
        }
    }

    /**
     * Delete the authenticated user's own comment or by admin.
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->authorizeCommentOwner($request->user(), $comment);

        try {
            DB::transaction(function () use ($comment) {
                $comment->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully.',
            ]);
        } catch (Throwable $e) {
            Log::error("CommentController: Failed to delete comment ID {$comment->id}. Error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment.',
            ], 500);
        }
    }

    /**
     * Authorize whether the current user can edit/delete the comment.
     */
    private function authorizeCommentOwner(User $user, Comment $comment): void
    {
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');

        if (! $isAdmin && $comment->user_id !== $user->id) {
            abort(403, 'You do not have permission to modify this comment.');
        }
    }
}
