<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('icon')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('files')) {
            Schema::table('files', function (Blueprint $table) {
                if (! Schema::hasColumn('files', 'title')) {
                    $table->string('title')->nullable();
                }
                if (! Schema::hasColumn('files', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('files', 'folder_id')) {
                    $table->unsignedBigInteger('folder_id')->nullable();
                }
                if (! Schema::hasColumn('files', 'category_id')) {
                    $table->unsignedBigInteger('category_id')->nullable();
                }
                if (! Schema::hasColumn('files', 'material_id')) {
                    $table->unsignedBigInteger('material_id')->nullable();
                }
                if (! Schema::hasColumn('files', 'filename')) {
                    $table->string('filename')->nullable();
                }
                if (! Schema::hasColumn('files', 'path')) {
                    $table->string('path')->nullable();
                }
                if (! Schema::hasColumn('files', 'file_type')) {
                    $table->string('file_type')->nullable();
                }
                if (! Schema::hasColumn('files', 'mime_type')) {
                    $table->string('mime_type')->nullable();
                }
                if (! Schema::hasColumn('files', 'file_size')) {
                    $table->unsignedBigInteger('file_size')->nullable();
                }
                if (! Schema::hasColumn('files', 'content_hash')) {
                    $table->string('content_hash')->nullable();
                }
                if (! Schema::hasColumn('files', 'status')) {
                    $table->string('status')->default('pending');
                }
                if (! Schema::hasColumn('files', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable();
                }
                if (! Schema::hasColumn('files', 'views_count')) {
                    $table->unsignedBigInteger('views_count')->default(0);
                }
                if (! Schema::hasColumn('files', 'downloads_count')) {
                    $table->unsignedBigInteger('downloads_count')->default(0);
                }
                if (! Schema::hasColumn('files', 'favorites_count')) {
                    $table->unsignedBigInteger('favorites_count')->default(0);
                }
                if (! Schema::hasColumn('files', 'shares_count')) {
                    $table->unsignedBigInteger('shares_count')->default(0);
                }
                if (! Schema::hasColumn('files', 'rating_avg')) {
                    $table->decimal('rating_avg', 4, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        // Keep shared catalog and settings data when rolling back this compatibility migration.
    }
};
