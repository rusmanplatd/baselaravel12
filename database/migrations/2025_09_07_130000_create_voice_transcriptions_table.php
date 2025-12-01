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
        Schema::create('voice_transcriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('message_id');
            $table->ulid('attachment_id');
            $table->text('transcript')->nullable();
            $table->string('language', 10)->nullable();
            $table->float('confidence')->nullable();
            $table->float('duration')->nullable();
            $table->integer('word_count')->nullable();
            $table->json('segments')->nullable();
            $table->string('status'); // pending, processing, completed, failed
            $table->string('provider')->default('openai-whisper');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            $table->foreign('attachment_id')->references('id')->on('message_files')->onDelete('cascade');
            
            $table->index(['message_id']);
            $table->index(['status', 'created_at']);
            $table->index(['language']);
            $table->index(['provider']);
            $table->fullText(['transcript']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_transcriptions');
    }
};