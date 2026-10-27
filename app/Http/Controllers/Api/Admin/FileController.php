<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FileController extends Controller
{
    private const STATS_CACHE_KEY = 'admin_library_statistics';

    /**
     * List files by status (pending | published | rejected | trashed).
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString();
        $userId = $request->user()->id;

        $query = File::query()->with([
            'category',
            'material',
            'user',
            'favorites' => fn($query) => $query->where('user_id', $userId),
            'ratings' => fn($query) => $query->where('user_id', $userId),
        ]);

        // فلترة بالبحث النصي
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where('title', 'like', "%{$q}%");
        }

        // فلترة بالحالة
        if (in_array($status, [File::STATUS_PENDING, File::STATUS_PUBLISHED, File::STATUS_REJECTED], true)) {
            $query->where('status', $status);
        } elseif ($status === 'trashed') {
            $query->onlyTrashed();
        } else {
            $query->whereIn('status', [File::STATUS_PUBLISHED, File::STATUS_PENDING]);
        }

        $files = $query->orderByDesc('created_at')->paginate($this->pageSize($request));

        return response()->json([
            'data' => FileResource::collection($files),
            'meta' => [
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    /**
     * Approve a pending file (publish it) & notify uploader.
     */
    public function approve(File $file): JsonResponse
    {
        DB::transaction(function () use ($file) {
            $file->update(['status' => File::STATUS_PUBLISHED]);
        });

        Cache::forget(self::STATS_CACHE_KEY);

        // إرسال إشعار للمستخدم الذي رفع الملف (إن وجد)
        if ($file->uploader) {
            // $file->uploader->notify(new \App\Notifications\FileApprovedNotification($file));
        }

        return response()->json(['message' => 'File published successfully.']);
    }

    /**
     * Reject a pending file with optional rejection reason.
     */
    public function reject(Request $request, File $file): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($file, $validated) {
            $file->update([
                'status' => File::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'] ?? null,
            ]);
        });

        Cache::forget(self::STATS_CACHE_KEY);

        if ($file->uploader) {
            // $file->uploader->notify(new \App\Notifications\FileRejectedNotification($file, $validated['reason'] ?? null));
        }

        return response()->json(['message' => 'File rejected.']);
    }

    /**
     * Re-open a rejected file to pending (re-review).
     */
    public function reopen(File $file): JsonResponse
    {
        DB::transaction(function () use ($file) {
            $file->update([
                'status' => File::STATUS_PENDING,
                'rejection_reason' => null,
            ]);
        });

        Cache::forget(self::STATS_CACHE_KEY);

        return response()->json(['message' => 'File moved back to review.']);
    }

    /**
     * Move a file to a different category/material.
     */
    public function move(Request $request, File $file): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'material_id' => ['required', 'exists:materials,id'],
        ]);

        DB::transaction(function () use ($file, $validated) {
            $file->update([
                'category_id' => $validated['category_id'],
                'material_id' => $validated['material_id'],
            ]);
        });

        return response()->json([
            'message' => 'File moved successfully.',
            'data' => new FileResource($file->fresh(['category', 'material'])),
        ]);
    }

    /**
     * Soft-delete a file (move to trash bin).
     */
    public function destroy(File $file): JsonResponse
    {
        DB::transaction(function () use ($file) {
            $file->delete();
        });

        Cache::forget(self::STATS_CACHE_KEY);

        return response()->json(['message' => 'File moved to trash.']);
    }

    /**
     * Restore a file from the trash bin.
     */
    public function restore(int $id): JsonResponse
    {
        $file = File::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($file) {
            $file->restore();
        });

        Cache::forget(self::STATS_CACHE_KEY);

        return response()->json(['message' => 'File restored successfully.']);
    }

    /**
     * Permanently delete a file and its storage file.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $file = File::onlyTrashed()->findOrFail($id);

        try {
            DB::transaction(function () use ($file) {
                // حذف الملف والصورة المصغرة من القرص الفيزيائي (تم تصحيح الخطأ هنا)
                $this->deletePhysicalFiles($file);

                // الحذف النهائي من قاعدة البيانات
                $file->forceDelete();
            });

            Cache::forget(self::STATS_CACHE_KEY);

            return response()->json(['message' => 'File permanently deleted from disk and database.']);
        } catch (Throwable $e) {
            Log::error("FileController: Failed to force delete file ID {$id}. Error: {$e->getMessage()}");

            return response()->json(['message' => 'Failed to permanently delete file.'], 500);
        }
    }

    /**
     * Empty the trash bin safely (deleting storage files first).
     */
    public function emptyTrash(): JsonResponse
    {
        $count = 0;
        $failedCount = 0;

        File::onlyTrashed()->chunkById(200, function ($trashedFiles) use (&$count, &$failedCount) {
            foreach ($trashedFiles as $file) {
                try {
                    DB::transaction(function () use ($file) {
                        $this->deletePhysicalFiles($file);
                        $file->forceDelete();
                    });
                    $count++;
                } catch (Throwable $e) {
                    Log::error("FileController: Failed to clear trashed file ID {$file->id}. Error: {$e->getMessage()}");
                    $failedCount++;
                }
            }
        });

        Cache::forget(self::STATS_CACHE_KEY);

        return response()->json([
            'message' => "Trash emptied successfully ({$count} files completely removed).",
            'failed_count' => $failedCount,
        ]);
    }

    /**
     * ميزة جديدة: الموافقات الجماعية على الملفات (Bulk Approve)
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:files,id'],
        ]);

        DB::transaction(function () use ($validated) {
            File::whereIn('id', $validated['ids'])
                ->where('status', File::STATUS_PENDING)
                ->update(['status' => File::STATUS_PUBLISHED]);
        });

        Cache::forget(self::STATS_CACHE_KEY);

        return response()->json(['message' => 'Selected files approved successfully.']);
    }

    /**
     * Admin library statistics with 10-minute caching.
     */
    public function statistics(): JsonResponse
    {
        $stats = Cache::remember(self::STATS_CACHE_KEY, 600, function () {
            $fileStats = DB::table('files')
                ->selectRaw('
                    COUNT(*) as total_files,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published_files,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_files,
                    SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as trashed_files,
                    COALESCE(SUM(downloads_count), 0) as total_downloads,
                    COALESCE(SUM(views_count), 0) as total_views
                ', [File::STATUS_PUBLISHED, File::STATUS_PENDING])
                ->first();

            return [
                'total_files' => (int) $fileStats->total_files,
                'published_files' => (int) $fileStats->published_files,
                'pending_files' => (int) $fileStats->pending_files,
                'trashed_files' => (int) $fileStats->trashed_files,
                'total_downloads' => (int) $fileStats->total_downloads,
                'total_views' => (int) $fileStats->total_views,
                'total_users' => User::count(),
            ];
        });

        return response()->json($stats);
    }

    /**
     * Helper function to clean physical storage files.
     */
    private function deletePhysicalFiles(File $file): void
    {
        $disk = Storage::disk('public');

        if (! empty($file->path) && $disk->exists($file->path)) {
            $disk->delete($file->path);
        }

        if (! empty($file->thumbnail) && $disk->exists($file->thumbnail)) {
            $disk->delete($file->thumbnail);
        }
    }
}
