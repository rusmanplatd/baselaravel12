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
        Schema::create('device_key_shares', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('from_device_id'); // Device sharing the key
            $table->ulid('to_device_id'); // Device receiving the key
            $table->ulid('conversation_id');
            $table->ulid('user_id');
            $table->text('encrypted_symmetric_key'); // Conversation key encrypted for the receiving device
            $table->text('from_device_public_key'); // Snapshot of sharing device's public key
            $table->text('to_device_public_key'); // Snapshot of receiving device's public key
            $table->integer('key_version')->default(1); // Version of the symmetric key being shared
            $table->string('share_method')->default('device_to_device'); // device_to_device, backup_restore, etc.
            $table->boolean('is_accepted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('from_device_id')->references('id')->on('user_devices')->cascadeOnDelete();
            $table->foreign('to_device_id')->references('id')->on('user_devices')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('sys_users')->cascadeOnDelete();

            $table->index(['to_device_id', 'is_accepted', 'is_active']);
            $table->index(['from_device_id', 'conversation_id']);
            $table->index(['user_id', 'conversation_id', 'is_active']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('device_key_shares');
    }
};
