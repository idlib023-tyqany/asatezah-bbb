<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $create = static function (string $table, Closure $definition): void {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $definition);
            }
        };

        $create('educational_stages', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        $create('grades', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stage_id')->constrained('educational_stages')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        $create('tracks', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $create('grade_track_pivot', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('track_id')->constrained('tracks')->cascadeOnDelete();
            $table->unique(['grade_id', 'track_id']);
        });

        $create('subjects', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        $create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable()->unique();
            $table->string('phone_number')->nullable()->unique();
            $table->string('avatar')->nullable();
            $table->string('specialization')->nullable();
            $table->string('display_name')->nullable();
            $table->enum('trainer_type', ['subject_teacher', 'class_teacher'])->nullable();
            $table->enum('role', ['teacher', 'admin'])->default('teacher')->index();
            $table->boolean('can_contribute')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_banned')->default(false);
            $table->string('status')->default('active');
            $table->timestamp('deactivated_at')->nullable();
            $table->string('fcm_token')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        foreach ([
            'phone_number' => static fn (Blueprint $table) => $table->string('phone_number')->nullable()->unique(),
            'specialization' => static fn (Blueprint $table) => $table->string('specialization')->nullable(),
            'display_name' => static fn (Blueprint $table) => $table->string('display_name')->nullable(),
            'trainer_type' => static fn (Blueprint $table) => $table->string('trainer_type')->nullable(),
            'can_contribute' => static fn (Blueprint $table) => $table->boolean('can_contribute')->default(false),
            'is_banned' => static fn (Blueprint $table) => $table->boolean('is_banned')->default(false),
            'status' => static fn (Blueprint $table) => $table->string('status')->default('active'),
            'deactivated_at' => static fn (Blueprint $table) => $table->timestamp('deactivated_at')->nullable(),
            'fcm_token' => static fn (Blueprint $table) => $table->string('fcm_token')->nullable(),
            'phone_verified_at' => static fn (Blueprint $table) => $table->timestamp('phone_verified_at')->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', $definition);
            }
        }

        $create('teacher_grade_subjects', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained('tracks')->nullOnDelete();
            $table->timestamps();
        });

        $create('categories', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        $create('materials', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->timestamps();
        });

        $create('settings', static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $create('folders', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('folders')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_root')->default(false);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        $create('folder_hierarchy', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_folder_id')->constrained('folders')->cascadeOnDelete();
            $table->foreignId('child_folder_id')->constrained('folders')->cascadeOnDelete();
            $table->unique(['parent_folder_id', 'child_folder_id']);
        });

        $create('files', static function (Blueprint $table): void {
            $table->string('id', 20)->primary();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('filename')->nullable();
            $table->string('path')->nullable();
            $table->string('file_path')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('file_type', 100)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('content_hash', 64)->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->unsignedBigInteger('favorites_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->decimal('rating_avg', 4, 2)->default(0);
            $table->decimal('avg_rating', 4, 2)->default(0);
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('ratings_total_count')->default(0);
            $table->unsignedInteger('comments_total_count')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        $create('favorites', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_id', 20);
            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'file_id']);
        });

        $create('ratings', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_id', 20);
            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->unsignedTinyInteger('value');
            $table->string('comment', 500)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'file_id']);
        });

        $create('comments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_id', 20);
            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
        });

        $create('reports', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_id', 20);
            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->string('category');
            $table->text('reason');
            $table->string('status')->default('open');
            $table->string('user_ip', 45)->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'file_id']);
        });

        $create('notifications', static function (Blueprint $table): void {
            $table->id();
            $table->string('audience')->default('all');
            $table->string('specialization')->nullable();
            $table->string('title');
            $table->text('body');
            $table->string('type')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        $create('user_notifications', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'notification_id']);
        });

        $create('support_conversations', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->string('status')->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        $create('messages', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $create('verification_challenges', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose');
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email_code_hash')->nullable();
            $table->string('phone_code_hash')->nullable();
            $table->json('channels');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('resends')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        $create('teacher_timetables', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('section')->default('أ');
            $table->string('classroom')->nullable();
            $table->unsignedInteger('reminder_minutes')->default(15);
            $table->timestamps();
        });

        $create('class_progress', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->string('section')->default('أ');
            $table->string('last_topic');
            $table->string('page_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'grade_id', 'section']);
        });

        $create('class_notes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->string('section')->default('أ');
            $table->text('note_content');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
        });

        $create('ai_chats', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->timestamps();
        });

        $create('ai_messages', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_id')->constrained('ai_chats')->cascadeOnDelete();
            $table->string('role');
            $table->string('content_type')->default('text');
            $table->longText('text_content')->nullable();
            $table->string('image_url')->nullable();
            $table->string('mode')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'ai_messages',
            'ai_chats',
            'class_notes',
            'class_progress',
            'teacher_timetables',
            'verification_challenges',
            'messages',
            'support_conversations',
            'user_notifications',
            'notifications',
            'reports',
            'comments',
            'ratings',
            'favorites',
            'files',
            'folder_hierarchy',
            'folders',
            'settings',
            'materials',
            'categories',
            'teacher_grade_subjects',
            'users',
            'subjects',
            'grade_track_pivot',
            'tracks',
            'grades',
            'educational_stages',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }
};
