<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\TeacherGeminiController;
use App\Http\Controllers\Api\TeacherToolsController;

// 1. الهيكل الدراسي
Route::get('/taxonomy', [TaxonomyController::class, 'getHierarchy']);

// 2. المجلدات والشجرة
Route::get('/folders/root', [FolderController::class, 'getRoot']);
Route::get('/folders/{id}', [FolderController::class, 'getFolderDetails']);

// 3. الملفات والمفضلة والبلاغات
Route::get('/files/{id}', [FileController::class, 'show']);
Route::post('/files/{id}/favorite', [FileController::class, 'toggleFavorite']);
Route::post('/files/{id}/report', [FileController::class, 'reportFile']);

// 4. مساعد الأستاذ Gemini (يشمل حاسبة الدرجات والواجبات)
Route::post('/ai/assist', [TeacherGeminiController::class, 'handle']);

// 5. أدوات الأستاذ اليومية (الجدول، أين توقفت، المفكرة)
Route::get('/timetable', [TeacherToolsController::class, 'getTimetable']);
Route::post('/timetable', [TeacherToolsController::class, 'addTimetableSlot']);
Route::get('/progress', [TeacherToolsController::class, 'getProgress']);
Route::post('/progress', [TeacherToolsController::class, 'updateProgress']);
Route::get('/notes', [TeacherToolsController::class, 'getNotes']);
Route::post('/notes', [TeacherToolsController::class, 'addNote']);
