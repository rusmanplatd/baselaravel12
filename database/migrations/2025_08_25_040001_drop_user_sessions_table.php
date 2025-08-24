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
        Schema::dropIfExists('user_sessions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the user_sessions table if rollback is needed
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('trusted_device_id')->nullable()->constrained('trusted_devices')->onDelete('set null');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('device_type')->nullable();
            $table->string('location')->nullable();
            $table->longText('payload');
            $table->timestamp('last_activity');
            $table->timestamp('login_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            $table->index(['user_id', 'is_active']);
            $table->index('last_activity');
            $table->index(['trusted_device_id', 'is_active']);
        });
    }
};
