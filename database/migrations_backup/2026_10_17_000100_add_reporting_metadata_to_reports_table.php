<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('category')->nullable()->after('file_id');
            $table->string('user_ip', 45)->nullable();
            $table->text('admin_notes')->nullable();
            $table->index(['user_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'file_id']);
            $table->dropColumn(['category', 'user_ip', 'admin_notes']);
        });
    }
};
