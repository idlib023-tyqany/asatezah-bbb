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
        $hasRole = Schema::hasColumn('users', 'role');
        $hasCanContribute = Schema::hasColumn('users', 'can_contribute');
        $hasSpecialization = Schema::hasColumn('users', 'specialization');
        $hasDeactivatedAt = Schema::hasColumn('users', 'deactivated_at');

        Schema::table('users', function (Blueprint $table) use (
            $hasRole,
            $hasCanContribute,
            $hasSpecialization,
            $hasDeactivatedAt
        ) {
            if (! $hasRole) {
                $table->string('role')->default('user')->index();
            }

            if (! $hasCanContribute) {
                $table->boolean('can_contribute')->default(true);
            }

            if (! $hasSpecialization) {
                $table->string('specialization')->nullable();
            }

            if (! $hasDeactivatedAt) {
                $table->timestamp('deactivated_at')->nullable()->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = array_values(array_filter(
            ['can_contribute', 'specialization', 'deactivated_at'],
            fn (string $column) => Schema::hasColumn('users', $column)
        ));

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
