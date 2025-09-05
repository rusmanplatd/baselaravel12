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
        // Signal Protocol Identity Keys (long-term Ed25519 keys)
        Schema::create('signal_identity_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('device_id')->constrained('user_devices')->onDelete('cascade');
            $table->text('public_key'); // Ed25519 public key (base64)
            $table->text('private_key_encrypted'); // Encrypted Ed25519 private key
            $table->text('key_fingerprint'); // SHA-256 hash of public key for verification
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
            $table->index('key_fingerprint');
        });

        // Signal Protocol Signed Pre-Keys (medium-term Curve25519 keys)
        Schema::create('signal_signed_prekeys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('device_id')->constrained('user_devices')->onDelete('cascade');
            $table->integer('key_id'); // Sequential key ID per device
            $table->text('public_key'); // Curve25519 public key (base64)
            $table->text('private_key_encrypted'); // Encrypted Curve25519 private key
            $table->text('signature'); // Ed25519 signature by identity key
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id', 'key_id']);
            $table->index(['user_id', 'device_id', 'is_active']);
            $table->index('expires_at');
        });

        // Signal Protocol One-Time Pre-Keys (single-use Curve25519 keys)
        Schema::create('signal_one_time_prekeys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('device_id')->constrained('user_devices')->onDelete('cascade');
            $table->integer('key_id'); // Sequential key ID per device
            $table->text('public_key'); // Curve25519 public key (base64)
            $table->text('private_key_encrypted'); // Encrypted Curve25519 private key
            $table->timestamp('used_at')->nullable(); // When this key was consumed
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id', 'key_id']);
            $table->index(['user_id', 'device_id', 'used_at']);
            $table->index(['expires_at', 'used_at']); // For cleanup
        });

        // Signal Protocol Sessions (Double Ratchet state)
        Schema::create('signal_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('local_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('local_device_id')->constrained('user_devices')->onDelete('cascade');
            $table->foreignUlid('remote_user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('remote_device_id')->constrained('user_devices')->onDelete('cascade');
            $table->text('session_state_encrypted'); // Encrypted Double Ratchet state
            $table->text('remote_identity_key'); // Remote device's identity key for verification
            $table->integer('message_counter')->default(0); // For message ordering
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->useCurrent();
            $table->timestamps();

            $table->unique(['local_user_id', 'local_device_id', 'remote_user_id', 'remote_device_id']);
            $table->index(['local_user_id', 'local_device_id', 'is_active']);
            $table->index('last_used_at');
        });

        // Post-Quantum Key Encapsulation (for ML-KEM integration)
        Schema::create('quantum_key_encapsulation', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('signal_sessions')->onDelete('cascade');
            $table->string('algorithm'); // ML-KEM-512, ML-KEM-768, ML-KEM-1024
            $table->text('public_key'); // ML-KEM public key
            $table->text('private_key_encrypted'); // Encrypted ML-KEM private key
            $table->text('shared_secret_encrypted')->nullable(); // Encrypted shared secret after encapsulation
            $table->text('ciphertext')->nullable(); // Ciphertext from key encapsulation
            $table->boolean('is_active')->default(true);
            $table->timestamp('established_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'is_active']);
            $table->index('algorithm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quantum_key_encapsulation');
        Schema::dropIfExists('signal_sessions');
        Schema::dropIfExists('signal_one_time_prekeys');
        Schema::dropIfExists('signal_signed_prekeys');
        Schema::dropIfExists('signal_identity_keys');
    }
};
