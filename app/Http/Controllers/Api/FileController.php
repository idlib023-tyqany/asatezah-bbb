<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Support\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FileController extends Controller
{
    /**
     * Display a paged listing of published files with filters & search.
     *
     * Sorts: latest (default) | most_downloaded | top_rated
     * Filters: category_id, material_id, min_rating, file_type
     */
    public function index(Request $request): mixed
    {
        try {
            $userId = $request->user('sanctum')?->id ?? $request->user()?->id;
            $relations = [
                'category:id,name,slug',
                'material:id,name,slug',
                'user:id,name,avatar,role',
            ];

            if ($userId !== null) {
                $relations['favorites'] = fn ($query) => $query->where('user_id', $userId);
                $relations['ratings'] = fn ($query) => $query->where('user_id', $userId);
            }

            $query = File::query()
                ->published()
                ->with($relations);

            // بوابة البحث الشامل في العناوين والوصف
            if ($request->filled('q')) {
                $q = $request->string('q')->toString();
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            }

            // فلترة التخصص / الصف
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->integer('category_id'));
            }

            // فلترة المادة
            if ($request->filled('material_id')) {
                $query->where('material_id', $request->integer('material_id'));
            }

            // فلترة التقييم الأصغر (دعم الأرقام العشرية)
            if ($request->filled('min_rating')) {
                $query->where('avg_rating', '>=', (float) $request->input('min_rating'));
            }

            // الترتيب: الأحدث (افتراضي) | الأكثر تحميلاً | الأعلى تقييماً
            match ($request->string('sort')->toString()) {
                'most_downloaded' => $query->mostDownloaded(),
                'top_rated' => $query->orderByDesc('avg_rating'),
                default => $query->latest(),
            };

            $files = $query->paginate($this->pageSize($request));

            return FileResource::collection($files);
        } catch (Throwable $e) {
            Log::error("FileController: Failed to fetch public files list. Error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to load files.',
            ], 500);
        }
    }

    /**
     * Display the specified file details & safely record unique views.
     */
    public function show(Request $request, File $file): mixed
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        try {
            $this->recordUniqueView($request, $file);

            $userId = $request->user('sanctum')?->id ?? $request->user()?->id;
            $relations = [
                'category:id,name,slug',
                'material:id,name,slug',
                'user:id,name,avatar,role',
            ];

            if ($userId !== null) {
                $relations['favorites'] = fn ($query) => $query->where('user_id', $userId);
                $relations['ratings'] = fn ($query) => $query->where('user_id', $userId);
            }

            return new FileResource($file->load($relations));
        } catch (Throwable $e) {
            Log::error("FileController: Failed to show file ID {$file->id}. Error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to load file details.',
            ], 500);
        }
    }

    /**
     * Record view count safely without spam.
     */
    private function recordUniqueView(Request $request, File $file): void
    {
        $userId = $request->user()?->id ?? $request->ip();
        $cacheKey = "viewed_file_{$file->id}_{$userId}";

        // منع تكرار زيادة العداد لنفس المستخدم خلال ساعة
        if (! Cache::has($cacheKey)) {
            DB::transaction(function () use ($file) {
                $file->increment('views_count');
            });
            Cache::put($cacheKey, true, now()->addHour());
        }
    }

    /**
     * Increment the download counter and stream the file securely using Storage facade.
     */
    public function download(Request $request, File $file)
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        // التحقق الآمن من وجود الملف على القرص
        $disk = FileStorage::disk();

        if (! $file->path || ! $disk->exists($file->path)) {
            abort(404, 'The requested file could not be found on the storage server.');
        }

        try {
            // زيادة عداد التنزيل بأمان داخل معاملة قاعدة بيانات
            DB::transaction(function () use ($file) {
                $file->increment('downloads_count');
            });

            $downloadFilename = $file->filename ?? basename($file->path);

            return $disk->download($file->path, $downloadFilename);
        } catch (Throwable $e) {
            Log::error("FileController: Failed to download file ID {$file->id}. Error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to download the file.',
            ], 500);
        }
    }
}
