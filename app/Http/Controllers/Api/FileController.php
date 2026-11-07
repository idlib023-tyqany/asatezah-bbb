<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Support\FileStorage;
use Illuminate\Http\Client\ConnectionException;
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
    /**
     * Upload a file and associate it with a folder.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canContribute(), 403, 'Your account is not allowed to upload files.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'file' => [
                'required',
                'file',
                'mimes:mp4,mov,avi,mkv,webm,pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,webp',
                'max:51200',
            ],
        ]);

        $uploadedFile = $request->file('file');
        $mimeType = $uploadedFile->getMimeType() ?? '';
        $directory = match (true) {
            str_starts_with($mimeType, 'video/') => 'videos',
            str_starts_with($mimeType, 'image/') => 'images',
            in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ], true) => 'documents',
            default => abort(422, 'The uploaded file type is not supported.'),
        };
        $contentHash = hash_file('sha256', $uploadedFile->getRealPath());

        if ($contentHash === false) {
            throw new RuntimeException('Unable to calculate the uploaded file hash.');
        }

        abort_if(
            File::query()->where('content_hash', $contentHash)->exists(),
            409,
            'This file has already been uploaded.'
        );

        $path = FileStorage::store($uploadedFile, $directory);

        try {
            $file = DB::transaction(function () use ($validated, $user, $uploadedFile, $mimeType, $contentHash, $path) {
                return File::create([
                    'uploaded_by' => $user->id,
                    'category_id' => $validated['category_id'] ?? null,
                    'material_id' => $validated['material_id'] ?? null,
                    'folder_id' => $validated['folder_id'] ?? null,
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'filename' => basename($uploadedFile->getClientOriginalName()),
                    'path' => $path,
                    'file_type' => $mimeType,
                    'mime_type' => $mimeType,
                    'file_size' => $uploadedFile->getSize(),
                    'content_hash' => $contentHash,
                    'status' => File::STATUS_PENDING,
                ]);
            });
        } catch (Throwable $exception) {
            FileStorage::disk()->delete($path);

            throw $exception;
        }

        return response()->json([
            'message' => 'File uploaded successfully and is awaiting review.',
            'data' => new FileResource($file->load([
                'category:id,name,slug',
                'material:id,name,slug',
                'user:id,name,avatar,role',
            ])),
        ], 201);
    }

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

    /**
     * Resolve a Telegram file ID to this application's protected download URL.
     */
    public function getDownloadUrl(string $id): JsonResponse
    {
        $this->getTelegramFilePath($id);

        return response()->json([
            'download_url' => route('api.files.telegram.download', ['id' => $id]),
        ]);
    }

    /**
     * Download a Telegram file without exposing the bot token to the client.
     */
    public function downloadTelegramFile(string $id)
    {
        $filePath = $this->getTelegramFilePath($id);
        $token = config('services.telegram.bot_token');

        try {
            $download = Http::timeout(60)
                ->get("https://api.telegram.org/file/bot{$token}/{$filePath}");
        } catch (ConnectionException) {
            Log::warning('Telegram file download request failed.');

            abort(502, 'The file could not be downloaded from Telegram.');
        }

        if (! $download->successful()) {
            Log::warning('Telegram file download returned an unsuccessful response.', [
                'status' => $download->status(),
            ]);

            abort(502, 'The file could not be downloaded from Telegram.');
        }

        return response()->streamDownload(
            static fn () => print $download->body(),
            basename($filePath),
            ['Content-Type' => $download->header('Content-Type', 'application/octet-stream')]
        );
    }

    private function getTelegramFilePath(string $fileId): string
    {
        $token = config('services.telegram.bot_token');

        if (! is_string($token) || $token === '') {
            Log::error('Telegram bot token is not configured.');

            abort(503, 'Telegram downloads are not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getFile", [
                    'file_id' => $fileId,
                ]);
        } catch (ConnectionException) {
            Log::warning('Telegram getFile request failed.');

            abort(502, 'Telegram could not resolve the requested file.');
        }

        $filePath = $response->json('result.file_path');

        if (! $response->successful() || $response->json('ok') !== true || ! is_string($filePath) || $filePath === '') {
            Log::warning('Telegram getFile returned an unsuccessful response.', [
                'status' => $response->status(),
            ]);

            abort(502, 'Telegram could not resolve the requested file.');
        }

        return $filePath;
    }
}
