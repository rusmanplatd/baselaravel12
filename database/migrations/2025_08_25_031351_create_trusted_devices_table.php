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
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->string('device_token')->unique();
            $table->string('device_name');
            $table->string('device_type')->nullable(); // mobile, desktop, tablet
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->ipAddress('ip_address');
            $table->string('user_agent')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Additional device info
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['device_token', 'is_active']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
