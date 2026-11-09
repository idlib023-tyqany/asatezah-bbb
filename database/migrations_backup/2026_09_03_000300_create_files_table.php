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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable(); // غلاف الملف أو الصورة المصغرة

            $table->string('filename');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);

            // Hash لمنع تكرار الملفات (SHA-256) - الـ unique يُنشئ Index تلقائياً
            $table->string('content_hash', 64)->unique();

            $table->enum('status', ['pending', 'published', 'rejected'])->default('pending')->index();

            // إحصائيات وتقييمات
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->unsignedBigInteger('favorites_count')->default(0);
            $table->decimal('avg_rating', 3, 2)->default(0.00);
            $table->unsignedInteger('ratings_count')->default(0);

            $table->softDeletes();
            $table->timestamps();

            // فهارس مركبّة فائقة السرعة لاستعلامات الـ API الشائعة
            $table->index(['material_id', 'status', 'created_at']);
            $table->index(['category_id', 'status', 'created_at']);
            $table->index(['uploaded_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
