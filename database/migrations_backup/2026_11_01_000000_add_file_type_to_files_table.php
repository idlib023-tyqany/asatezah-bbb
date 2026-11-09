<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->string('file_type')->nullable()->after('thumbnail');
        });

        DB::table('files')
            ->whereNull('file_type')
            ->update(['file_type' => DB::raw('mime_type')]);
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('file_type');
        });
    }
};
