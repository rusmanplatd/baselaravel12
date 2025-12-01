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
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('conversation_id');
            $table->ulid('sender_id');
            $table->text('content');
            $table->string('content_type')->default('text');
            $table->timestamp('scheduled_for');
            $table->string('timezone', 50)->default('UTC');
            $table->string('status'); // scheduled, sending, sent, failed, cancelled
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->text('error_message')->nullable();
            $table->ulid('sent_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('sent_message_id')->references('id')->on('chat_messages')->onDelete('set null');
            
            $table->index(['status', 'scheduled_for']);
            $table->index(['conversation_id', 'status']);
            $table->index(['sender_id', 'status']);
            $table->index(['scheduled_for']);
            $table->index(['status', 'retry_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_messages');
    }
};