<?php

use Illuminate\Support\Facades\Route;

/**
 * Application status endpoint for Flutter API integration.
 */
Route::get('/', function () {
    return response()->json([
        'app' => config('app.name', 'asatezah'),
        'status' => 'online',
        'message' => 'Asatezah API is running smoothly.',
    ]);
});

// التعامل مع الروابط التي لا توجد في الـ Web
Route::fallback(function () {
    return response()->json([
        'message' => 'Resource not found. Please verify the API endpoint.',
    ], 404);
});
