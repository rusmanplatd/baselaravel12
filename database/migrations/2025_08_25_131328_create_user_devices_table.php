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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->string('device_name'); // User-friendly device name
            $table->string('device_type')->default('unknown'); // mobile, desktop, web, etc.
            $table->text('public_key'); // Device-specific public key
            $table->string('device_fingerprint')->unique(); // Unique device identifier
            $table->string('platform')->nullable(); // iOS, Android, Windows, etc.
            $table->string('user_agent')->nullable(); // Browser user agent
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('sys_users')->cascadeOnDelete();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'is_trusted']);
            $table->index('device_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('user_devices');
    }
};
