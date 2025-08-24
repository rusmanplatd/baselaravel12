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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('conversation_id');
            $table->ulid('sender_id');
            $table->ulid('reply_to_id')->nullable();
            $table->enum('type', ['text', 'image', 'file', 'system'])->default('text');
            $table->longText('encrypted_content');
            $table->text('content_hash');
            $table->text('content_hmac')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('sys_users')->cascadeOnDelete();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['type', 'status']);
            $table->index('content_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
