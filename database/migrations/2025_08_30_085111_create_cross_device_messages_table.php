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
        Schema::create('cross_device_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 64)->unique();
            $table->char('conversation_id', 26);
            $table->char('sender_id', 26);
            $table->char('sender_device_id', 26)->nullable();
            $table->json('target_devices')->nullable();
            $table->json('encrypted_for_devices')->nullable();
            $table->boolean('quantum_safe')->default(true);
            $table->json('encryption_metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index('conversation_id');
            $table->index('sender_id');
            $table->index('sender_device_id');
            $table->index(['conversation_id', 'created_at']);
            $table->index(['expires_at']);
            $table->index(['quantum_safe', 'created_at']);

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('sender_device_id')->references('id')->on('user_devices')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cross_device_messages');
    }
};
