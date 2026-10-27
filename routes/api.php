<?php

use App\Http\Controllers\Api\Admin\CatalogController as AdminCatalogController;
// Public & Auth Controllers
use App\Http\Controllers\Api\Admin\FileController as AdminFileController;
use App\Http\Controllers\Api\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AppInfoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\FavoriteController;
// Admin Controllers
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/google', [SocialAuthController::class, 'handleGoogleCallback'])
    ->middleware('throttle:6,1');
Route::post('/auth/facebook', [SocialAuthController::class, 'handleFacebookCallback'])
    ->middleware('throttle:6,1');

/*
|--------------------------------------------------------------------------
| Public & Guest Routes
|--------------------------------------------------------------------------
*/
// حماية مسارات التسجيل والدخول من المحاولات المكررة (6 محاولات بالدقيقة)
Route::middleware('throttle:6,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/info', [AppInfoController::class, 'show']);
Route::get('/filters', [CatalogController::class, 'filters']);

Route::prefix('files')->group(function () {
    Route::get('/', [FileController::class, 'index']);
    Route::get('/{file}', [FileController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Authenticated User Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Profile & Auth
    Route::get('/user', fn () => request()->user());
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::put('/password', [AuthController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAllDevices']);
    Route::delete('/account', [AuthController::class, 'destroy']);

    // Upload & Downloads
    Route::post('/upload', [UploadController::class, 'store'])->middleware('throttle:uploads');
    Route::get('/files/{file}/download', [FileController::class, 'download'])
        ->name('api.files.download');

    // Favorites
    Route::prefix('favorites')->group(function () {
        Route::get('/', [FavoriteController::class, 'index']);
        Route::post('/{file}', [FavoriteController::class, 'store']);
        Route::delete('/{file}', [FavoriteController::class, 'destroy']);
    });

    // Ratings
    Route::post('/ratings/{file}', [RatingController::class, 'store']);
    Route::delete('/ratings/{file}', [RatingController::class, 'destroy']);

    // Comments
    Route::get('/files/{file}/comments', [CommentController::class, 'index']);
    Route::post('/files/{file}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    // Reports
    Route::post('/reports/{file}', [ReportController::class, 'store']);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{notification}/read', [NotificationController::class, 'read']);
        Route::patch('/{notification}/read', [NotificationController::class, 'read']);
        Route::post('/read-all', [NotificationController::class, 'readAll']);
        Route::patch('/read-all', [NotificationController::class, 'readAll']);
    });

    // Support
    Route::prefix('support')->group(function () {
        Route::get('/', [SupportController::class, 'inbox']);
        Route::post('/', [SupportController::class, 'store']);
        Route::get('/{conversation}/messages', [SupportController::class, 'messages']);
        Route::post('/{conversation}/messages', [SupportController::class, 'send']);
        Route::post('/{conversation}/close', [SupportController::class, 'close']);
        Route::patch('/{conversation}/close', [SupportController::class, 'close']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Users Management
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::post('/{user}/contribution', [AdminUserController::class, 'setContribution']);
        Route::delete('/{user}', [AdminUserController::class, 'destroy']);
    });

    // Files Management & Trash
    Route::prefix('files')->group(function () {
        Route::get('/', [AdminFileController::class, 'index']);
        Route::post('/{file}/approve', [AdminFileController::class, 'approve']);
        Route::post('/{file}/reject', [AdminFileController::class, 'reject']);
        Route::post('/{file}/reopen', [AdminFileController::class, 'reopen']);
        Route::put('/{file}/move', [AdminFileController::class, 'move']);
        Route::delete('/{file}', [AdminFileController::class, 'destroy']);
        Route::post('/{id}/restore', [AdminFileController::class, 'restore']);
        Route::delete('/{id}/force', [AdminFileController::class, 'forceDelete']);
    });

    Route::post('/trash/empty', [AdminFileController::class, 'emptyTrash']);
    Route::get('/statistics', [AdminFileController::class, 'statistics']);

    // Categories & Materials
    Route::get('/categories', [AdminCatalogController::class, 'categories']);
    Route::post('/categories', [AdminCatalogController::class, 'storeCategory']);
    Route::put('/categories/{category}', [AdminCatalogController::class, 'updateCategory']);
    Route::delete('/categories/{category}', [AdminCatalogController::class, 'destroyCategory']);

    Route::get('/materials', [AdminCatalogController::class, 'materials']);
    Route::post('/materials', [AdminCatalogController::class, 'storeMaterial']);
    Route::put('/materials/{material}', [AdminCatalogController::class, 'updateMaterial']);
    Route::delete('/materials/{material}', [AdminCatalogController::class, 'destroyMaterial']);

    // Reports Management
    Route::prefix('reports')->group(function () {
        Route::get('/', [AdminReportController::class, 'index']);
        Route::patch('/{report}/resolve', [AdminReportController::class, 'resolve']);
        Route::patch('/{report}/dismiss', [AdminReportController::class, 'dismiss']);
    });

    // Notifications Management
    Route::prefix('notifications')->group(function () {
        Route::get('/', [AdminNotificationController::class, 'index']);
        Route::post('/all', [AdminNotificationController::class, 'sendToAll']);
        Route::post('/specialization', [AdminNotificationController::class, 'sendToSpecialization']);
    });

    // Support Management
    Route::prefix('support')->group(function () {
        Route::get('/', [AdminSupportController::class, 'index']);
        Route::post('/{conversation}/claim', [AdminSupportController::class, 'claim']);
    });

    // Settings
    Route::get('/settings', [AdminSettingsController::class, 'show']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);
    Route::post('/settings/logo', [AdminSettingsController::class, 'uploadLogo']);
});
