<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\TeacherGeminiController;
use App\Http\Controllers\Api\TeacherToolsController;

// 1. مسارات التوافق مع الاختبارات القديمة
Route::get('/filters', function () {
    $categories = DB::table('categories')->get();
    $stages = DB::table('educational_stages')->get();
    return response()->json(['status' => 'success', 'categories' => $categories, 'stages' => $stages]);
});

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::post('/categories', function (Request $request) {
        $id = DB::table('categories')->insertGetId([
            'name'       => $request->name,
            'slug'       => \Illuminate\Support\Str::slug($request->name),
            'icon'       => $request->icon ?? 'folder',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['id' => $id, 'name' => $request->name], 201);
    });

    Route::patch('/files/{id}/approve', function ($id) {
        DB::table('files')->where('id', $id)->update(['status' => 'approved']);
        return response()->json(['status' => 'success', 'message' => 'Approved']);
    });

    Route::patch('/files/{id}/reject', function (Request $request, $id) {
        DB::table('files')->where('id', $id)->update(['status' => 'rejected', 'rejection_reason' => $request->reason]);
        return response()->json(['status' => 'success', 'message' => 'Rejected']);
    });

    Route::patch('/files/{id}/reopen', function ($id) {
        DB::table('files')->where('id', $id)->update(['status' => 'pending']);
        return response()->json(['status' => 'success', 'message' => 'Reopened']);
    });

    Route::post('/settings', function (Request $request) {
        foreach ($request->all() as $k => $v) {
            DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now()]);
        }
        return response()->json(['status' => 'success']);
    });
});

// 2. الهيكل الدراسي والمجلدات والملفات
Route::get('/taxonomy', [TaxonomyController::class, 'getHierarchy']);
Route::get('/folders/root', [FolderController::class, 'getRoot']);
Route::get('/folders/{id}', [FolderController::class, 'getFolderDetails']);

// 3. الملفات والمفضلة والبلاغات
Route::get('/files/{id}', [FileController::class, 'show']);
Route::post('/files/{id}/favorite', [FileController::class, 'toggleFavorite']);
Route::post('/files/{id}/report', [FileController::class, 'reportFile']);

// 4. مساعد الأستاذ Gemini
Route::post('/ai/assist', [TeacherGeminiController::class, 'handle']);

// 5. أدوات الأستاذ اليومية
Route::get('/timetable', [TeacherToolsController::class, 'getTimetable']);
Route::post('/timetable', [TeacherToolsController::class, 'addTimetableSlot']);
Route::get('/progress', [TeacherToolsController::class, 'getProgress']);
Route::post('/progress', [TeacherToolsController::class, 'updateProgress']);
Route::get('/notes', [TeacherToolsController::class, 'getNotes']);
Route::post('/notes', [TeacherToolsController::class, 'addNote']);
