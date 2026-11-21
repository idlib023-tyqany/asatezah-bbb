<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        Schema::table('files', function (Blueprint $table) {
            if (! Schema::hasColumn('files', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! Schema::hasIndex('files', 'idx_files_status_created')) {
            Schema::table('files', fn (Blueprint $table) => $table->index(['status', 'created_at'], 'idx_files_status_created'));
        }
        if (! Schema::hasIndex('files', 'idx_files_hash')) {
            Schema::table('files', fn (Blueprint $table) => $table->index('content_hash', 'idx_files_hash'));
        }
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
