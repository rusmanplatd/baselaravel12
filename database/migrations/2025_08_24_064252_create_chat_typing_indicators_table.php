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
        Schema::create('chat_typing_indicators', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('conversation_id');
            $table->ulid('user_id');
            $table->boolean('is_typing')->default(false);
            $table->timestampTz('last_typed_at')->useCurrent();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['conversation_id', 'is_typing']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_typing_indicators');
    }
};
