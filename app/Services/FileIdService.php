<?php

namespace App\Services;

use App\Models\File;

class FileIdService
{
    public static function generate20DigitId(): string
    {
        do {
            $timestamp = (string) round(microtime(true) * 1000);
            $randomPadding = str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
            $id = $timestamp . $randomPadding;
        } while (File::where('id', $id)->exists());

        return $id;
    }
}
