<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter(
            ['provider', 'provider_id'],
            fn (string $column): bool => Schema::hasColumn('users', $column)
        ));

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['provider', 'provider_id'],
            fn (string $column): bool => ! Schema::hasColumn('users', $column)
        ));

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
    }
};
