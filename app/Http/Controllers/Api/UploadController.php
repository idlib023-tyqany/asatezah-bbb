<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Support\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class UploadController extends Controller
{
    /**
     * Upload a file for review.
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
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'filename' => basename($uploadedFile->getClientOriginalName()),
                    'path' => $path,
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
}
