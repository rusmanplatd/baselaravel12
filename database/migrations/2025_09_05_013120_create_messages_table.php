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
        // Main messages table
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->ulid('id');
            $table->foreignUlid('conversation_id');
            $table->foreignUlid('sender_id');
            $table->foreignUlid('sender_device_id');
            $table->enum('message_type', ['text', 'image', 'video', 'audio', 'file', 'voice', 'poll', 'system', 'call']);
            $table->text('encrypted_content'); // Encrypted message content
            $table->json('encrypted_metadata')->nullable(); // Encrypted metadata (mentions, formatting, etc.)
            $table->text('content_hash'); // SHA-256 hash for integrity verification
            $table->string('encryption_algorithm')->default('signal'); // 'signal', 'quantum', 'hybrid'
            $table->integer('encryption_version')->default(1); // For algorithm versioning
            $table->foreignUlid('thread_id')->nullable(); // For threaded messages
            $table->foreignUlid('reply_to_id')->nullable(); // For replies
            $table->json('delivery_status')->nullable(); // JSON with per-device delivery status
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // For disappearing messages
            $table->timestamps();

            $table->primary('id');

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('sender_device_id')->references('id')->on('user_devices')->onDelete('cascade');
            $table->foreign('thread_id')->references('id')->on('chat_messages')->onDelete('cascade');
            $table->foreign('reply_to_id')->references('id')->on('chat_messages')->onDelete('set null');

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['thread_id', 'created_at']);
            $table->index(['message_type', 'is_deleted']);
            $table->index('expires_at');
        });

        // Message reactions
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('device_id')->constrained('user_devices')->onDelete('cascade');
            $table->string('reaction_type'); // emoji or custom reaction
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'device_id', 'reaction_type']);
            $table->index(['message_id', 'reaction_type']);
        });

        // Message attachments (encrypted files, images, videos)
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->string('filename');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->text('encrypted_file_key'); // Key to decrypt the file
            $table->text('encrypted_storage_path'); // Encrypted path to file in storage
            $table->text('file_hash'); // SHA-256 hash for integrity
            $table->text('thumbnail_encrypted')->nullable(); // Encrypted thumbnail for images/videos
            $table->json('metadata')->nullable(); // File metadata (dimensions, duration, etc.)
            $table->string('encryption_algorithm')->default('aes-256-gcm');
            $table->timestamps();

            $table->index(['message_id', 'mime_type']);
        });

        // Message mentions
        Schema::create('message_mentions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->foreignUlid('mentioned_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->enum('mention_type', ['user', 'all', 'here'])->default('user');
            $table->integer('start_position'); // Position in the decrypted message
            $table->integer('length'); // Length of the mention
            $table->timestamps();

            $table->index(['message_id', 'mention_type']);
            $table->index(['mentioned_user_id', 'created_at']);
        });

        // Message delivery receipts
        Schema::create('message_delivery_receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->foreignUlid('recipient_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('recipient_device_id')->constrained('user_devices')->onDelete('cascade');
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'recipient_user_id', 'recipient_device_id']);
            $table->index(['recipient_user_id', 'status']);
            $table->index(['message_id', 'status']);
        });

        // Poll messages (encrypted polls and surveys)
        Schema::create('message_polls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->text('encrypted_question'); // Encrypted poll question
            $table->json('encrypted_options'); // JSON array of encrypted options
            $table->boolean('allows_multiple_choices')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['message_id', 'is_active']);
            $table->index('expires_at');
        });

        // Poll votes (encrypted)
        Schema::create('message_poll_votes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('poll_id')->constrained('message_polls')->onDelete('cascade');
            $table->foreignUlid('voter_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('voter_device_id')->constrained('user_devices')->onDelete('cascade');
            $table->text('encrypted_choices'); // Encrypted array of selected option indexes
            $table->text('vote_hash'); // Hash for vote integrity without revealing choice
            $table->timestamps();

            $table->unique(['poll_id', 'voter_user_id', 'voter_device_id']);
            $table->index(['poll_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_poll_votes');
        Schema::dropIfExists('message_polls');
        Schema::dropIfExists('message_delivery_receipts');
        Schema::dropIfExists('message_mentions');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('chat_messages');
    }
};
