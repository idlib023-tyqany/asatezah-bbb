<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول الإعدادات
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // 2. جدول التصنيفات
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('icon')->nullable();
                $table->timestamps();
            });
        }

        // 3. إضافة الأعمدة المتوافقة لجدول files
        Schema::table('files', function (Blueprint $table) {
            if (!Schema::hasColumn('files', 'title')) $table->string('title')->nullable();
            if (!Schema::hasColumn('files', 'filename')) $table->string('filename')->nullable();
            if (!Schema::hasColumn('files', 'path')) $table->string('path')->nullable();
            if (!Schema::hasColumn('files', 'mime_type')) $table->string('mime_type')->nullable();
            if (!Schema::hasColumn('files', 'content_hash')) $table->string('content_hash')->nullable();
            if (!Schema::hasColumn('files', 'status')) $table->string('status')->default('approved');
            if (!Schema::hasColumn('files', 'rejection_reason')) $table->text('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('settings');
    }
};
