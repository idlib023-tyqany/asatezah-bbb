<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول الحصص الأسبوعي مع وقت البداية والنهاية والتنبيه
        Schema::create('teacher_timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->enum('day_of_week', ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
            $table->time('start_time'); // وقت بداية الحصة
            $table->time('end_time');   // وقت نهاية الحصة
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('section')->default('أ'); // الشعبة (أ، ب، ج...)
            $table->string('classroom')->nullable(); // القاعة أو اسم المدرسة
            $table->integer('reminder_minutes')->default(15); // التنبيه المسبق قبل الحصة بدقائق
            $table->timestamps();
        });

        // 2. متتبع تقدم الشعبة (أين توقفت؟)
        Schema::create('class_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->string('section')->default('أ');
            $table->string('last_topic'); // عنوان الدرس الذي توقف عنده
            $table->string('page_number')->nullable(); // رقم الصفحة
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. مفكرة الملاحظات السريعة للصف
        Schema::create('class_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->string('section')->default('أ');
            $table->text('note_content');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_notes');
        Schema::dropIfExists('class_progress');
        Schema::dropIfExists('teacher_timetables');
    }
};
