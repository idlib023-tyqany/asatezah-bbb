<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Support\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'file' => [
                'required',
                'file',
                'mimes:mp4,mov,avi,mkv,webm,pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,webp',
                'extensions:mp4,mov,avi,mkv,webm,pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,webp',
                'max:51200',
            ],
        ]);

        $uploadedFile = $request->file('file');
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($uploadedFile->getRealPath()) ?: '';
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $mimeTypesByExtension = [
            'mp4' => ['video/mp4'],
            'mov' => ['video/quicktime'],
            'avi' => ['video/x-msvideo'],
            'mkv' => ['video/x-matroska'],
            'webm' => ['video/webm'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
        ];
        if (! in_array($mimeType, $mimeTypesByExtension[$extension] ?? [], true)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file extension does not match its content type.',
            ]);
        }

        $directory = match (true) {
            in_array($mimeType, ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'video/webm'], true) => 'videos',
            in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) => 'images',
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
                    'name' => $validated['title'],
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
}
