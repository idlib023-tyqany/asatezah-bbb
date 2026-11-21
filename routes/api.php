<?php

use App\Http\Controllers\Api\Admin\CatalogController as AdminCatalogController;
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
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\TeacherGeminiController;
use App\Http\Controllers\Api\TeacherToolsController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verification/confirm', [AuthController::class, 'verify']);
    Route::post('/verification/resend', [AuthController::class, 'resendVerification']);
});

Route::get('/info', [AppInfoController::class, 'show']);
Route::get('/app-info', [AppInfoController::class, 'show']);

Route::middleware(['auth:sanctum', 'teacher'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/contact-change', [AuthController::class, 'requestContactChange'])
        ->middleware('throttle:10,1');
    Route::post('/profile/contact-change/verify', [AuthController::class, 'verifyContactChange'])
        ->middleware('throttle:10,1');
    Route::post('/profile/contact-change/resend', [AuthController::class, 'resendContactChange'])
        ->middleware('throttle:10,1');
    Route::put('/password', [AuthController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAllDevices']);
    Route::delete('/account', [AuthController::class, 'destroy']);

    Route::get('/filters', [CatalogController::class, 'filters']);
    Route::get('/taxonomy', [TaxonomyController::class, 'getHierarchy']);
    Route::get('/folders', [FolderController::class, 'index']);
    Route::get('/folders/root', [FolderController::class, 'getRoot']);
    Route::get('/folders/{id}', [FolderController::class, 'getFolderDetails'])->whereNumber('id');

    Route::get('/files', [FileController::class, 'index'])->name('api.files.index');
    Route::get('/files/{file}/download', [FileController::class, 'download'])
        ->name('api.files.download');
    Route::get('/files/{file}', [FileController::class, 'show'])->name('api.files.show');
    Route::get('/files/telegram/{id}/download-url', [FileController::class, 'telegramDownloadUrl'])
        ->where('id', '[A-Za-z0-9_-]+');
    Route::get('/files/telegram/{id}/download', [FileController::class, 'downloadTelegram'])
        ->where('id', '[A-Za-z0-9_-]+')
        ->name('api.files.telegram.download');
    Route::post('/files/{file}/favorite', [FileController::class, 'toggleFavorite']);
    Route::post('/files/{file}/report', [FileController::class, 'reportFile']);
    Route::get('/files/{file}/comments', [CommentController::class, 'index']);
    Route::post('/files/{file}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{file}', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{file}', [FavoriteController::class, 'destroy']);
    Route::get('/ratings/{file}', [RatingController::class, 'show']);
    Route::post('/ratings/{file}', [RatingController::class, 'store']);
    Route::delete('/ratings/{file}', [RatingController::class, 'destroy']);
    Route::post('/reports/{file}', [ReportController::class, 'store']);
    Route::get('/reports/{file}/status', [ReportController::class, 'status']);

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{notification}/read', [NotificationController::class, 'read']);
        Route::patch('/{notification}/read', [NotificationController::class, 'read']);
        Route::post('/read-all', [NotificationController::class, 'readAll']);
        Route::patch('/read-all', [NotificationController::class, 'readAll']);
    });

    Route::prefix('support')->group(function () {
        Route::get('/', [SupportController::class, 'inbox']);
        Route::post('/', [SupportController::class, 'store']);
        Route::get('/{conversation}/messages', [SupportController::class, 'messages']);
        Route::post('/{conversation}/messages', [SupportController::class, 'send']);
        Route::post('/{conversation}/close', [SupportController::class, 'close']);
        Route::patch('/{conversation}/close', [SupportController::class, 'close']);
    });

    Route::post('/upload', [UploadController::class, 'store'])
        ->middleware(['can_contribute', 'throttle:10,1']);
    Route::post('/ai/assist', [TeacherGeminiController::class, 'handle'])
        ->middleware('throttle:15,1');

    foreach (['timetable' => 'Timetable', 'progress' => 'Progress', 'notes' => 'Notes'] as $tool => $label) {
        Route::get("/tools/{$tool}", [TeacherToolsController::class, "get{$label}"]);
        Route::post("/tools/{$tool}", [
            TeacherToolsController::class,
            match ($tool) {
                'timetable' => 'addTimetableSlot',
                'progress' => 'updateProgress',
                default => 'addNote',
            },
        ]);
        Route::get("/{$tool}", [TeacherToolsController::class, "get{$label}"]);
        Route::post("/{$tool}", [
            TeacherToolsController::class,
            match ($tool) {
                'timetable' => 'addTimetableSlot',
                'progress' => 'updateProgress',
                default => 'addNote',
            },
        ]);
    }
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::put('/users/{user}', [AdminUserController::class, 'update']);
    Route::post('/users/{user}/contribution', [AdminUserController::class, 'setContribution']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

    Route::get('/files', [AdminFileController::class, 'index']);
    Route::patch('/files/{file}/approve', [AdminFileController::class, 'approve']);
    Route::post('/files/{file}/approve', [AdminFileController::class, 'approve']);
    Route::patch('/files/{file}/reject', [AdminFileController::class, 'reject']);
    Route::post('/files/{file}/reject', [AdminFileController::class, 'reject']);
    Route::patch('/files/{file}/reopen', [AdminFileController::class, 'reopen']);
    Route::post('/files/{file}/reopen', [AdminFileController::class, 'reopen']);
    Route::put('/files/{file}/move', [AdminFileController::class, 'move']);
    Route::delete('/files/{file}', [AdminFileController::class, 'destroy']);
    Route::post('/files/{id}/restore', [AdminFileController::class, 'restore'])->whereNumber('id');
    Route::delete('/files/{id}/force', [AdminFileController::class, 'forceDelete']);
    Route::post('/files/bulk-approve', [AdminFileController::class, 'bulkApprove']);
    Route::post('/trash/empty', [AdminFileController::class, 'emptyTrash']);
    Route::get('/statistics', [AdminFileController::class, 'statistics']);

    Route::get('/categories', [AdminCatalogController::class, 'categories']);
    Route::post('/categories', [AdminCatalogController::class, 'storeCategory']);
    Route::put('/categories/{category}', [AdminCatalogController::class, 'updateCategory']);
    Route::delete('/categories/{category}', [AdminCatalogController::class, 'destroyCategory']);
    Route::get('/materials', [AdminCatalogController::class, 'materials']);
    Route::post('/materials', [AdminCatalogController::class, 'storeMaterial']);
    Route::put('/materials/{material}', [AdminCatalogController::class, 'updateMaterial']);
    Route::delete('/materials/{material}', [AdminCatalogController::class, 'destroyMaterial']);

    Route::get('/reports', [AdminReportController::class, 'index']);
    Route::patch('/reports/{report}/resolve', [AdminReportController::class, 'resolve']);
    Route::patch('/reports/{report}/dismiss', [AdminReportController::class, 'dismiss']);
    Route::delete('/reports/{report}', [AdminReportController::class, 'destroy']);
    Route::delete('/reports', [AdminReportController::class, 'bulkDestroy']);
    Route::get('/reports/statistics', [AdminReportController::class, 'statistics']);
    Route::get('/notifications', [AdminNotificationController::class, 'index']);
    Route::post('/notifications/all', [AdminNotificationController::class, 'sendToAll']);
    Route::post('/notifications/specialization', [AdminNotificationController::class, 'sendToSpecialization']);
    Route::post('/notifications/user', [AdminNotificationController::class, 'sendToUser']);
    Route::delete('/notifications/{notification}', [AdminNotificationController::class, 'destroy']);
    Route::delete('/notifications', [AdminNotificationController::class, 'bulkDestroy']);
    Route::get('/notifications/statistics', [AdminNotificationController::class, 'statistics']);
    Route::put('/folders/{folder}', [FolderController::class, 'update']);
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy']);
    Route::get('/support', [AdminSupportController::class, 'index']);
    Route::get('/support/{conversation}', [AdminSupportController::class, 'show']);
    Route::post('/support/{conversation}/claim', [AdminSupportController::class, 'claim']);
    Route::get('/settings', [AdminSettingsController::class, 'show']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);
    Route::post('/settings/logo', [AdminSettingsController::class, 'uploadLogo']);
});
