<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chat Threads
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('conversation_id');
            $table->ulid('parent_message_id');
            $table->ulid('creator_id');
            $table->string('title')->nullable();
            $table->text('encrypted_title')->nullable();
            $table->string('title_hash')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('participant_count')->default(0);
            $table->integer('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->ulid('last_message_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            $table->foreign('parent_message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('last_message_id')->references('id')->on('chat_messages')->onDelete('set null');

            $table->index(['conversation_id', 'is_active']);
            $table->index(['parent_message_id']);
            $table->index(['last_message_at']);
            $table->index(['creator_id']);
        });

        // Chat Thread Participants
        Schema::create('chat_thread_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('thread_id');
            $table->ulid('user_id');
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->ulid('last_read_message_id')->nullable();
            $table->json('notification_settings')->nullable();
            $table->timestamps();

            $table->foreign('thread_id')->references('id')->on('chat_threads')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('last_read_message_id')->references('id')->on('chat_messages')->onDelete('set null');

            $table->unique(['thread_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
            $table->index(['thread_id', 'left_at']);
        });

        // thread_id column and constraints already exist in chat_messages table
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['thread_id']);
            $table->dropColumn('thread_id');
        });

        Schema::dropIfExists('chat_thread_participants');
        Schema::dropIfExists('chat_threads');
    }
};
