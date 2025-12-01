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
        Schema::create('bot_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('bot_id');
            $table->ulid('conversation_id');
            $table->ulid('bot_conversation_id');
            $table->ulid('message_id')->nullable();
            $table->string('direction'); // incoming, outgoing
            $table->text('content')->nullable();
            $table->text('encrypted_content')->nullable();
            $table->integer('encryption_version')->default(1);
            $table->string('content_type')->default('text');
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('response_sent_at')->nullable();
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            $table->foreign('bot_conversation_id')->references('id')->on('bot_conversations')->onDelete('cascade');
            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            
            $table->index(['bot_id', 'direction', 'created_at']);
            $table->index(['conversation_id', 'created_at']);
            $table->index(['processed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_messages');
    }
};