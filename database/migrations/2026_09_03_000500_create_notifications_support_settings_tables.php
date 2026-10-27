<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. جدول الإشعارات العامة والجامعية
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('audience', ['all', 'specialization', 'role'])->default('all')->index();
            $table->string('specialization')->nullable()->index();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable(); // لحفظ بيانات إضافية مثل Deeplink للتطبيق
            $table->timestamps();
        });

        // 2. جدول تتبع قراءة الإشعارات للمستخدمين
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'notification_id']);
            $table->index(['user_id', 'read']); // تسريع جلب الإشعارات غير المقروءة
        });

        // 3. جدول محادثات الدعم الفني (Tickets)
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->enum('status', ['open', 'pending', 'closed'])->default('open')->index();
            $table->timestamp('last_message_at')->nullable()->index(); // لترتيب المحادثات حسب الأحدث
            $table->timestamps();
        });

        // 4. جدول رسائل الدعم الفني
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // لحذف الرسائل القديمة تلقائياً عند الحاجة
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
        });

        // 5. جدول إعدادات النظام
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('support_conversations');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('notifications');
    }
};
