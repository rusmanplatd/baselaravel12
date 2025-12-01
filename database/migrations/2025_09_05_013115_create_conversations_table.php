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
        // Main conversations table
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->enum('type', ['direct', 'group', 'channel'])->default('direct');
            $table->string('name')->nullable(); // For group chats and channels
            $table->text('description')->nullable();
            $table->text('avatar_url')->nullable();
            $table->json('settings')->nullable(); // JSON for conversation settings
            $table->foreignUlid('created_by_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('created_by_device_id')->nullable()->constrained('user_devices')->onDelete('set null');
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['organization_id', 'type']);
            $table->index('last_activity_at');
        });

        // Conversation participants
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('device_id')->nullable()->constrained('user_devices')->onDelete('set null');
            $table->enum('role', ['admin', 'moderator', 'member'])->default('member');
            $table->json('permissions')->nullable(); // JSON for custom permissions
            $table->boolean('is_muted')->default(false);
            $table->boolean('has_notifications')->default(true);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->ulid('last_read_message_id')->nullable(); // Will add foreign key after messages table exists
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id', 'device_id']);
            $table->index(['user_id', 'left_at']);
            $table->index(['conversation_id', 'role']);
        });

        // Conversation key bundles for group E2EE
        Schema::create('conversation_key_bundles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('participant_id')->constrained('conversation_participants')->onDelete('cascade');
            $table->text('encrypted_group_key'); // Encrypted with participant's public key
            $table->string('encryption_algorithm')->default('signal'); // 'signal', 'quantum', 'hybrid'
            $table->integer('key_version')->default(1); // For key rotation
            $table->boolean('is_active')->default(true);
            $table->timestamp('distributed_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'participant_id', 'key_version']);
            $table->index(['conversation_id', 'is_active']);
            $table->index(['participant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
