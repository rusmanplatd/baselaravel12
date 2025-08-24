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
        Schema::create('chat_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('conversation_id');
            $table->ulid('user_id');
            $table->enum('role', ['member', 'admin', 'owner'])->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->json('permissions')->nullable();
            $table->json('notification_settings')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('sys_users')->cascadeOnDelete();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'joined_at']);
            $table->index(['conversation_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_participants');
    }
};
