<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\Favorite;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FavoriteController extends Controller
{
    /**
     * List the authenticated user's favorite published files with pagination.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $user = $request->user();

            // جلب الملفات المضافة للمفضلة والتي تكون حالتها published فقط
            $userId = $user->id;
            $favoriteFiles = File::query()
                ->whereHas('favorites', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->published()
                ->with([
                    'category:id,name,slug',
                    'material:id,name,slug',
                    'user:id,name,avatar,role',
                    'favorites' => fn ($query) => $query->where('user_id', $userId),
                    'ratings' => fn ($query) => $query->where('user_id', $userId),
                ])
                ->orderByDesc(
                    Favorite::query()
                        ->select('created_at')
                        ->whereColumn('file_id', 'files.id')
                        ->where('user_id', $userId)
                        ->latest()
                        ->limit(1)
                )
                ->paginate($this->pageSize($request, 15));

            return FileResource::collection($favoriteFiles);
        } catch (Throwable $e) {
            Log::error("FavoriteController: Failed to fetch favorites list. Error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to load favorite files.',
            ], 500);
        }
    }

    /**
     * Toggle favorite status (Add if not exists, remove if exists).
     */
    public function toggle(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        try {
            $user = $request->user();

            $isFavorite = DB::transaction(function () use ($user, $file) {
                $favorite = $user->favorites()->where('file_id', $file->id)->first();

                if ($favorite) {
                    $favorite->delete();
                    $file->decrement('favorites_count');

                    return false;
                }

                $user->favorites()->create(['file_id' => $file->id]);
                $file->increment('favorites_count');

                return true;
            });

            return response()->json([
                'message' => $isFavorite ? 'Added to favorites.' : 'Removed from favorites.',
                'is_favorite' => $isFavorite,
                'favorites_count' => max(0, $file->fresh()->favorites_count),
            ]);
        } catch (Throwable $e) {
            Log::error("FavoriteController: Failed to toggle favorite for file ID {$file->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to update favorite status.',
            ], 500);
        }
    }

    /**
     * Explicitly add a file to the authenticated user's favorites.
     */
    public function store(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        try {
            $user = $request->user();

            DB::transaction(function () use ($user, $file) {
                $created = $user->favorites()->firstOrCreate(['file_id' => $file->id]);

                if ($created->wasRecentlyCreated) {
                    $file->increment('favorites_count');
                }
            });

            return response()->json([
                'message' => 'Added to favorites successfully.',
                'is_favorite' => true,
                'favorites_count' => $file->fresh()->favorites_count,
            ]);
        } catch (Throwable $e) {
            Log::error("FavoriteController: Failed to store favorite for file ID {$file->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to add to favorites.',
            ], 500);
        }
    }

    /**
     * Explicitly remove a file from the authenticated user's favorites.
     */
    public function destroy(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        try {
            $user = $request->user();

            DB::transaction(function () use ($user, $file) {
                $deleted = $user->favorites()->where('file_id', $file->id)->delete();

                if ($deleted) {
                    $file->decrement('favorites_count');
                }
            });

            return response()->json([
                'message' => 'Removed from favorites successfully.',
                'is_favorite' => false,
                'favorites_count' => max(0, $file->fresh()->favorites_count),
            ]);
        } catch (Throwable $e) {
            Log::error("FavoriteController: Failed to destroy favorite for file ID {$file->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to remove from favorites.',
            ], 500);
        }
    }
}
