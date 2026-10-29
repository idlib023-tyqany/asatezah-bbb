<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class FileStorage
{
    public static function disk(): FilesystemAdapter
    {
        return Storage::disk(config('filesystems.default'));
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, config('filesystems.default'));

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store the uploaded file.');
        }

        return $path;
    }

    public static function url(?string $path): ?string
    {
        return $path !== null && $path !== '' ? self::disk()->url($path) : null;
    }
}
