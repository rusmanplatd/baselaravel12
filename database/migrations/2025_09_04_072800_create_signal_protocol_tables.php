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
        // Identity keys table
        Schema::create('signal_identity_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->integer('registration_id')->unique();
            $table->text('public_key'); // Base64 encoded public key
            $table->text('private_key_encrypted'); // Encrypted private key
            $table->string('key_fingerprint', 64); // SHA-256 hash of public key
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');

            $table->index(['user_id', 'is_active']);
            $table->index('registration_id');
        });

        // Signed prekeys table
        Schema::create('signal_signed_prekeys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->integer('key_id');
            $table->text('public_key'); // Base64 encoded public key
            $table->text('private_key_encrypted'); // Encrypted private key
            $table->text('signature'); // Base64 encoded signature
            $table->timestamp('generated_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');

            $table->unique(['user_id', 'key_id']);
            $table->index(['user_id', 'is_active']);
        });

        // One-time prekeys table
        Schema::create('signal_onetime_prekeys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->integer('key_id');
            $table->text('public_key'); // Base64 encoded public key
            $table->text('private_key_encrypted'); // Encrypted private key
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->ulid('used_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('used_by_user_id')->references('id')->on('sys_users')->onDelete('set null');

            $table->unique(['user_id', 'key_id']);
            $table->index(['user_id', 'is_used']);
            $table->index('is_used');
        });

        // Signal sessions table
        Schema::create('signal_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('session_id')->unique();
            $table->ulid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->ulid('local_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->ulid('remote_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->ulid('local_device_id')->nullable()->constrained('user_devices')->onDelete('cascade');
            $table->ulid('remote_device_id')->nullable()->constrained('user_devices')->onDelete('cascade');
            $table->integer('local_registration_id');
            $table->integer('remote_registration_id');
            $table->text('remote_identity_key'); // Base64 encoded
            $table->text('session_state_encrypted'); // Encrypted ratchet state
            $table->integer('current_sending_chain')->default(0);
            $table->integer('current_receiving_chain')->default(0);
            $table->boolean('is_active')->default(true);
            $table->enum('verification_status', ['unverified', 'verified', 'trusted'])->default('unverified');
            $table->string('protocol_version', 10)->default('3.0');
            $table->integer('messages_sent')->default(0);
            $table->integer('messages_received')->default(0);
            $table->integer('key_rotations')->default(0);
            $table->timestamp('last_activity_at');
            $table->timestamps();

            $table->index(['conversation_id', 'is_active']);
            $table->index(['local_user_id', 'remote_user_id']);
            $table->index('last_activity_at');
        });

        // Signal messages table
        Schema::create('signal_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('message_id')->unique();
            $table->ulid('conversation_id');
            $table->ulid('session_id');
            $table->ulid('sender_user_id');
            $table->ulid('recipient_user_id');
            $table->enum('message_type', ['prekey', 'normal'])->default('normal');
            $table->integer('protocol_version')->default(3);
            $table->integer('registration_id')->nullable();
            $table->integer('prekey_id')->nullable();
            $table->integer('signed_prekey_id')->nullable();
            $table->text('base_key')->nullable(); // Base64 encoded ephemeral key
            $table->text('identity_key')->nullable(); // Base64 encoded
            $table->json('ratchet_message'); // Double ratchet message data
            $table->json('delivery_options'); // Priority, receipts, etc.
            $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->timestamp('sent_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('signal_sessions')->onDelete('cascade');
            $table->foreign('sender_user_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('recipient_user_id')->references('id')->on('sys_users')->onDelete('cascade');

            $table->index(['conversation_id', 'sent_at']);
            $table->index(['sender_user_id', 'recipient_user_id']);
            $table->index('delivery_status');
        });

        // Prekey bundle requests/sharing log
        Schema::create('signal_prekey_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('requester_user_id');
            $table->ulid('target_user_id');
            $table->ulid('identity_key_id');
            $table->ulid('signed_prekey_id');
            $table->ulid('onetime_prekey_id')->nullable();
            
            $table->foreign('requester_user_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('identity_key_id')->references('id')->on('signal_identity_keys')->onDelete('cascade');
            $table->foreign('signed_prekey_id')->references('id')->on('signal_signed_prekeys')->onDelete('cascade');
            $table->foreign('onetime_prekey_id')->references('id')->on('signal_onetime_prekeys')->onDelete('set null');
            $table->string('request_id')->unique();
            $table->json('bundle_data'); // Full prekey bundle
            $table->boolean('is_consumed')->default(false);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['requester_user_id', 'target_user_id']);
            $table->index('is_consumed');
        });

        // Key rotation log
        Schema::create('signal_key_rotations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('session_id')->nullable();
            $table->foreign('session_id')->references('id')->on('signal_sessions')->onDelete('cascade');
            $table->enum('rotation_type', ['identity', 'signed_prekey', 'onetime_prekeys', 'session_keys']);
            $table->string('old_key_id')->nullable();
            $table->string('new_key_id')->nullable();
            $table->string('reason')->nullable();
            $table->json('rotation_metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');

            $table->index(['user_id', 'rotation_type']);
            $table->index('created_at');
        });

        // Identity verification attempts
        Schema::create('signal_identity_verifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('verifier_user_id');
            $table->ulid('target_user_id');
            $table->ulid('session_id');
            
            $table->foreign('verifier_user_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('signal_sessions')->onDelete('cascade');
            $table->string('verification_method'); // 'fingerprint', 'qr_code', 'safety_numbers'
            $table->string('provided_fingerprint')->nullable();
            $table->string('actual_fingerprint');
            $table->boolean('verification_successful');
            $table->string('verification_token')->nullable();
            $table->json('verification_metadata')->nullable();
            $table->timestamps();

            $table->index(['verifier_user_id', 'target_user_id']);
            $table->index('verification_successful');
        });

        // Protocol statistics and health monitoring
        Schema::create('signal_protocol_stats', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->integer('active_sessions')->default(0);
            $table->integer('total_messages_sent')->default(0);
            $table->integer('total_messages_received')->default(0);
            $table->integer('key_rotations_performed')->default(0);
            $table->integer('identity_verifications')->default(0);
            $table->integer('prekey_refreshes')->default(0);
            $table->integer('available_onetime_prekeys')->default(0);
            $table->timestamp('last_prekey_refresh')->nullable();
            $table->timestamp('last_session_activity')->nullable();
            $table->json('health_metrics')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');

            $table->index('user_id');
            $table->index('last_session_activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signal_protocol_stats');
        Schema::dropIfExists('signal_identity_verifications');
        Schema::dropIfExists('signal_key_rotations');
        Schema::dropIfExists('signal_prekey_requests');
        Schema::dropIfExists('signal_messages');
        Schema::dropIfExists('signal_sessions');
        Schema::dropIfExists('signal_onetime_prekeys');
        Schema::dropIfExists('signal_signed_prekeys');
        Schema::dropIfExists('signal_identity_keys');
    }
};
