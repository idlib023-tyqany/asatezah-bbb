<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Support\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FileController extends Controller
{
    public function index(Request $request): mixed
    {
        $userId = $request->user()->id;
        $relations = [
            'category:id,name,slug',
            'material:id,name,slug',
            'user:id,name,avatar,role',
            'favorites' => fn ($query) => $query->where('user_id', $userId),
            'ratings' => fn ($query) => $query->where('user_id', $userId),
        ];
        $query = File::query()->published()->with($relations);

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($builder) use ($term): void {
                $builder->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        foreach (['category_id', 'material_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }

        match ($request->string('sort')->toString()) {
            'most_downloaded' => $query->mostDownloaded(),
            'top_rated' => $query->orderByDesc('avg_rating'),
            default => $query->latest(),
        };

        return FileResource::collection($query->paginate($this->pageSize($request)));
    }

    public function show(Request $request, File $file): mixed
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        $cacheKey = 'viewed_file_'.$file->id.'_'.$request->user()->id;
        if (! Cache::has($cacheKey)) {
            DB::transaction(fn () => $file->increment('views_count'));
            Cache::put($cacheKey, true, now()->addHour());
        }

        return new FileResource($file->load([
            'category:id,name,slug',
            'material:id,name,slug',
            'user:id,name,avatar,role',
            'favorites' => fn ($query) => $query->where('user_id', $request->user()->id),
            'ratings' => fn ($query) => $query->where('user_id', $request->user()->id),
        ]));
    }

    public function download(File $file)
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        $disk = FileStorage::disk();
        abort_unless($file->path && $disk->exists($file->path), 404, 'The requested file could not be found.');

        DB::transaction(fn () => $file->increment('downloads_count'));

        return response()->streamDownload(function () use ($disk, $file): void {
            $stream = $disk->readStream($file->path);
            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to read the requested file.');
            }

            fpassthru($stream);
            fclose($stream);
        }, basename($file->filename ?: $file->path));
    }

    public function toggleFavorite(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');
        $user = $request->user();

        $isFavorite = DB::transaction(function () use ($user, $file): bool {
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
            'status' => 'success',
            'is_favorite' => $isFavorite,
            'favorites_count' => max(0, (int) $file->fresh()->favorites_count),
        ]);
    }

    public function reportFile(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        DB::table('reports')->updateOrInsert(
            ['user_id' => $request->user()->id, 'file_id' => $file->id],
            [
                'category' => $validated['reason'],
                'reason' => $validated['message'],
                'status' => 'open',
                'user_ip' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return response()->json(['status' => 'success', 'message' => 'تم استلام بلاغك وسيقوم الأدمن بمراجعته.'], 201);
    }

    public function telegramDownloadUrl(string $id): JsonResponse
    {
        try {
            $this->telegramFilePath($id);
        } catch (Throwable $exception) {
            Log::error('Telegram file lookup failed.', ['exception' => $exception]);

            return response()->json(['message' => 'تعذر تجهيز رابط تنزيل الملف حالياً.'], 502);
        }

        return response()->json([
            'download_url' => route('api.files.telegram.download', ['id' => $id]),
        ]);
    }

    public function downloadTelegram(string $id)
    {
        try {
            $filePath = $this->telegramFilePath($id);
            $token = config('services.telegram.bot_token');
            $response = Http::timeout(30)
                ->get("https://api.telegram.org/file/bot{$token}/{$filePath}")
                ->throw();
        } catch (Throwable $exception) {
            Log::error('Telegram file proxy failed.', ['exception' => $exception]);

            return response()->json(['message' => 'تعذر تنزيل الملف حالياً.'], 502);
        }

        return response()->streamDownload(
            static fn () => print ($response->body()),
            basename($filePath),
            ['Content-Type' => $response->header('Content-Type', 'application/octet-stream')],
        );
    }

    private function telegramFilePath(string $id): string
    {
        $token = config('services.telegram.bot_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Telegram integration is not configured.');
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->get("https://api.telegram.org/bot{$token}/getFile", ['file_id' => $id])
            ->throw();
        $filePath = $response->json('result.file_path');

        if ($response->json('ok') !== true || ! is_string($filePath) || $filePath === '' || str_contains($filePath, '..')) {
            throw new RuntimeException('Telegram returned an invalid file path.');
        }

        return $filePath;
    }
}
