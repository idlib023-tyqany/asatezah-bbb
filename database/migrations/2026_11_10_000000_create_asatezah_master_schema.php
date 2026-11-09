<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. المراحل الدراسية
        Schema::dropIfExists('educational_stages');
        Schema::create('educational_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        // 2. الصفوف
        Schema::dropIfExists('grades');
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('educational_stages')->cascadeOnDelete();
            $table->string('name');
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        // 3. الأقسام والفروع
        Schema::dropIfExists('tracks');
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. ربط الصفوف بالأقسام
        Schema::dropIfExists('grade_track_pivot');
        Schema::create('grade_track_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('track_id')->constrained('tracks')->cascadeOnDelete();
        });

        // 5. المواد الدراسية
        Schema::dropIfExists('subjects');
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        // 6. المعلمون والمستخدمون
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_title');
            $table->string('first_name');
            $table->string('father_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->enum('teacher_type', ['subject_teacher', 'class_teacher'])->default('subject_teacher');
            $table->enum('status', ['active', 'banned', 'pending'])->default('pending');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // 7. ازدواجيات المعلم (المادة + الصف)
        Schema::dropIfExists('teacher_grade_subjects');
        Schema::create('teacher_grade_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained('tracks')->nullOnDelete();
            $table->timestamps();
        });

        // 8. شجرة المجلدات الهرمية
        Schema::dropIfExists('folders');
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_root')->default(false);
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        // 9. تعدد الآباء للمجلدات
        Schema::dropIfExists('folder_hierarchy');
        Schema::create('folder_hierarchy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_folder_id')->constrained('folders')->cascadeOnDelete();
            $table->foreignId('child_folder_id')->constrained('folders')->cascadeOnDelete();
        });

        // 10. الملفات التعليمية (بـ ID من 20 رقماً فريداً)
        Schema::dropIfExists('files');
        Schema::create('files', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('folder_id')->constrained('folders')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('file_type', 20);
            $table->string('file_path');
            $table->string('file_size', 30);
            $table->string('thumbnail_url')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->unsignedBigInteger('favorites_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->unsignedInteger('ratings_total_count')->default(0);
            $table->unsignedInteger('comments_total_count')->default(0);
            $table->timestamps();
        });

        // 11. المفضلة
        Schema::dropIfExists('favorites');
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('file_id');
            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'file_id']);
        });

        // 12. التقييمات والتعليقات
        Schema::dropIfExists('file_ratings');
        Schema::create('file_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id');
            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('stars')->unsigned();
            $table->timestamps();
        });

        Schema::dropIfExists('file_comments');
        Schema::create('file_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id');
            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
        });

        // 13. البلاغات والدعم الفني
        Schema::dropIfExists('file_reports');
        Schema::create('file_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id');
            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->text('message');
            $table->enum('status', ['pending', 'resolved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('support_tickets');
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject');
            $table->text('message');
            $table->enum('status', ['open', 'answered', 'closed'])->default('open');
            $table->text('admin_reply')->nullable();
            $table->timestamps();
        });

        // 14. أكواد التحقق (OTP)
        Schema::dropIfExists('verification_codes');
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('channel', ['email', 'phone']);
            $table->string('code', 8);
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
        });

        // 15. الذكاء الاصطناعي
        Schema::dropIfExists('ai_chats');
        Schema::create('ai_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('title');
            $table->timestamps();
        });

        Schema::dropIfExists('ai_messages');
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('ai_chats')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->enum('content_type', ['text', 'image', 'docx_converted'])->default('text');
            $table->longText('text_content')->nullable();
            $table->string('image_url')->nullable();
            $table->string('mode')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_chats');
        Schema::dropIfExists('verification_codes');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('file_reports');
        Schema::dropIfExists('file_comments');
        Schema::dropIfExists('file_ratings');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('files');
        Schema::dropIfExists('folder_hierarchy');
        Schema::dropIfExists('folders');
        Schema::dropIfExists('teacher_grade_subjects');
        Schema::dropIfExists('users');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('grade_track_pivot');
        Schema::dropIfExists('tracks');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('educational_stages');
        Schema::enableForeignKeyConstraints();
    }
};
