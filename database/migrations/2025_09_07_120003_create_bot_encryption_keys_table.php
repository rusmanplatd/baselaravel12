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
        Schema::create('bot_encryption_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('bot_id');
            $table->ulid('conversation_id');
            $table->string('key_type'); // primary, backup, rotated
            $table->string('algorithm'); // RSA-4096-OAEP, ML-KEM-768, HYBRID-RSA4096-MLKEM768
            $table->text('public_key');
            $table->text('encrypted_private_key');
            $table->string('key_pair_id');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            
            $table->unique(['bot_id', 'conversation_id', 'key_pair_id']);
            $table->index(['bot_id', 'is_active']);
            $table->index(['conversation_id', 'is_active']);
            $table->index(['algorithm']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_encryption_keys');
    }
};