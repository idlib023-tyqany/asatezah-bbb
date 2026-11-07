<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        });

        Schema::create('verification_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('email')->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('email_code_hash')->nullable();
            $table->string('phone_code_hash')->nullable();
            $table->json('channels');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('resends')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'purpose']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_challenges');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone_verified_at');
        });
    }
};
