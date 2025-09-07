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
        Schema::create('chat_encryption_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('device_id')->nullable()->constrained('user_devices')->onDelete('cascade');
            $table->text('encrypted_key'); // Encrypted symmetric key for conversation
            $table->text('public_key')->nullable(); // Public key used for encryption
            $table->string('device_fingerprint')->nullable(); // Device identification
            $table->integer('key_version')->default(1); // For key rotation
            $table->foreignUlid('created_by_device_id')->nullable()->constrained('user_devices')->onDelete('set null');
            $table->string('algorithm')->default('RSA-4096-OAEP'); // Encryption algorithm
            $table->integer('key_strength')->default(4096); // Key strength in bits
            $table->timestamp('expires_at')->nullable(); // Key expiration
            $table->boolean('is_active')->default(true); // Active status
            $table->timestamp('last_used_at')->nullable(); // Last usage tracking
            $table->json('device_metadata')->nullable(); // Device-specific metadata
            $table->timestamp('revoked_at')->nullable(); // Revocation timestamp
            $table->string('revocation_reason')->nullable(); // Reason for revocation
            $table->timestamps();

            // Indexes
            $table->unique(['conversation_id', 'user_id', 'device_id', 'key_version']);
            $table->index(['conversation_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index(['device_id', 'is_active']);
            $table->index(['algorithm', 'key_strength']);
            $table->index(['expires_at', 'is_active']);
            $table->index('revoked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_encryption_keys');
    }
};
