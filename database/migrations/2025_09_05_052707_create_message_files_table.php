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
        Schema::create('message_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->string('original_filename');
            $table->string('encrypted_filename');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->bigInteger('encrypted_size');
            $table->string('file_hash');
            $table->json('encryption_key_encrypted');
            $table->string('thumbnail_path')->nullable();
            $table->boolean('thumbnail_encrypted')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'created_at']);
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_files');
    }
};
