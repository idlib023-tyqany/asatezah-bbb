<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    /**
     * Get the authenticated user's current rating for a specific file.
     */
    public function show(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        $rating = Rating::where('user_id', $request->user()->id)
            ->where('file_id', $file->id)
            ->first();

        return response()->json([
            'my_rating' => $rating ? $rating->value : null,
            'comment' => $rating ? $rating->comment : null,
            'avg_rating' => round((float) ($file->avg_rating ?? 0), 1),
            'ratings_count' => (int) ($file->ratings_count ?? 0),
        ]);
    }

    /**
     * Create or update the authenticated user's rating for a published file.
     */
    public function store(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        $validated = $request->validate([
            'value' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $file, $validated) {
            Rating::updateOrCreate(
                ['user_id' => $user->id, 'file_id' => $file->id],
                [
                    'value' => $validated['value'],
                    'comment' => $validated['comment'] ?? null,
                ]
            );

            // إعادة حساب المتوسط العام وعدد التقييمات فوراً
            $this->recomputeAverage($file);
        });

        $file->refresh();

        return response()->json([
            'message' => 'Rating submitted successfully.',
            'my_rating' => (int) $validated['value'],
            'avg_rating' => round((float) $file->avg_rating, 1),
            'ratings_count' => (int) $file->ratings_count,
        ]);
    }

    /**
     * Delete the authenticated user's rating for a file.
     */
    public function destroy(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        $user = $request->user();

        DB::transaction(function () use ($user, $file) {
            $deleted = Rating::where('user_id', $user->id)
                ->where('file_id', $file->id)
                ->delete();

            if ($deleted) {
                $this->recomputeAverage($file);
            }
        });

        $file->refresh();

        return response()->json([
            'message' => 'Rating deleted successfully.',
            'my_rating' => null,
            'avg_rating' => round((float) $file->avg_rating, 1),
            'ratings_count' => (int) $file->ratings_count,
        ]);
    }

    /**
     * Recompute the file's average rating and total ratings count in a single SQL query.
     */
    private function recomputeAverage(File $file): void
    {
        $stats = Rating::query()
            ->where('file_id', $file->id)
            ->selectRaw('COUNT(*) as total_count, AVG(value) as avg_value')
            ->first();

        $count = $stats?->total_count ?? 0;
        $avg = $stats?->avg_value ? round((float) $stats->avg_value, 2) : 0.00;

        $file->update([
            'avg_rating' => $avg,
            'ratings_count' => $count,
        ]);
    }
}
