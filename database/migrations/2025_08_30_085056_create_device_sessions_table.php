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
        Schema::create('device_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('user_device_id', 26);
            $table->string('session_id', 64)->nullable();
            $table->json('conversation_keys')->nullable();
            $table->timestamp('last_key_sync_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('sync_metadata')->nullable();
            $table->timestamps();

            $table->index('user_device_id');
            $table->index(['user_device_id', 'is_active']);
            $table->index(['is_active', 'last_key_sync_at']);
            
            $table->foreign('user_device_id')->references('id')->on('user_devices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_sessions');
    }
};
