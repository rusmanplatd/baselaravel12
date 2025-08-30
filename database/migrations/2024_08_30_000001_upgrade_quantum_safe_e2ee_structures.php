<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for quantum-safe E2EE upgrade.
     * BREAKING CHANGES: This migration introduces breaking changes for enhanced quantum security.
     */
    public function up(): void
    {
        // Upgrade user_devices table for quantum-safe multi-device support
        Schema::table('user_devices', function (Blueprint $table) {
            // Remove old single public key structure
            $table->dropColumn(['public_key']);
            
            // Add enhanced quantum-safe key structure
            $table->json('identity_public_key')->nullable()->comment('ML-DSA-87 identity public key');
            $table->json('kem_public_key')->nullable()->comment('ML-KEM-1024 key encapsulation public key');
            $table->json('hybrid_kem_public_key')->nullable()->comment('FrodoKEM-1344 hybrid KEM public key');
            $table->json('backup_public_key')->nullable()->comment('SLH-DSA backup signature public key');
            $table->json('secondary_backup_public_key')->nullable()->comment('Secondary ML-DSA backup key');
            
            // Enhanced security properties
            $table->unsignedInteger('quantum_strength')->default(512)->comment('Quantum resistance bits');
            $table->boolean('sidechannel_resistant')->default(true)->comment('Side-channel attack resistance');
            $table->boolean('fault_injection_resistant')->default(true)->comment('Fault injection resistance');
            $table->string('quantum_implementation')->default('v3.0-MaxSec')->comment('Quantum implementation version');
            
            // Update existing quantum fields
            $table->unsignedTinyInteger('quantum_security_level')->default(5)->change()->comment('NIST security level (5 only)');
            $table->json('quantum_algorithms')->nullable()->comment('Supported quantum-safe algorithms');
            
            // Enhanced metadata
            $table->unsignedInteger('quantum_epoch')->nullable()->comment('Current quantum epoch');
            $table->unsignedInteger('ratchet_generation')->default(0)->comment('Forward secrecy ratchet generation');
            $table->timestamp('last_quantum_rotation')->nullable()->comment('Last quantum key rotation');
            
            // Threat detection
            $table->json('threat_metrics')->nullable()->comment('Quantum threat detection metrics');
            $table->unsignedInteger('failed_decryptions')->default(0)->comment('Failed decryption count');
            $table->boolean('suspicious_activity')->default(false)->comment('Suspicious activity detected');
            $table->enum('threat_level', ['low', 'medium', 'high', 'critical'])->default('low')->comment('Current threat level');
            
            // Compliance tracking
            $table->boolean('nist_compliant')->default(true)->comment('NIST standard compliance');
            $table->boolean('fips_approved')->default(false)->comment('FIPS approval status');
            $table->boolean('common_criteria_evaluated')->default(false)->comment('Common Criteria evaluation');
            
            // Performance metrics
            $table->unsignedInteger('avg_encryption_time')->nullable()->comment('Average encryption time (ms)');
            $table->unsignedInteger('avg_decryption_time')->nullable()->comment('Average decryption time (ms)');
            $table->unsignedInteger('avg_key_generation_time')->nullable()->comment('Average key generation time (ms)');
            
            // Audit trail
            $table->json('security_audit_trail')->nullable()->comment('Security event audit trail');
            
            // Indexes for performance
            $table->index(['user_id', 'quantum_ready']);
            $table->index(['quantum_security_level', 'is_trusted']);
            $table->index(['threat_level', 'suspicious_activity']);
        });

        // Create quantum conversation states table
        Schema::create('quantum_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id')->index()->comment('Chat conversation identifier');
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('User ID');
            
            // Quantum-safe key pairs (encrypted at rest)
            $table->json('current_key_pair')->comment('Current ML-KEM-1024 key pair (encrypted)');
            $table->json('next_key_pair')->comment('Next ML-KEM-1024 key pair (encrypted)');
            $table->json('hybrid_key_pair')->nullable()->comment('FrodoKEM-1344 key pair (encrypted)');
            
            // Enhanced double ratchet state
            $table->binary('root_key')->comment('Root key for double ratchet (encrypted)');
            $table->binary('sending_chain_key')->comment('Sending chain key (encrypted)');
            $table->binary('receiving_chain_key')->comment('Receiving chain key (encrypted)');
            
            // Message counters for forward secrecy
            $table->unsignedBigInteger('sending_message_number')->default(0);
            $table->unsignedBigInteger('receiving_message_number')->default(0);
            $table->unsignedBigInteger('previous_chain_length')->default(0);
            
            // Enhanced quantum parameters
            $table->unsignedInteger('quantum_epoch')->comment('Current quantum epoch');
            $table->timestamp('last_key_rotation')->nullable()->comment('Last key rotation timestamp');
            $table->unsignedInteger('key_rotation_interval')->default(300000)->comment('Key rotation interval (ms)');
            $table->unsignedInteger('ratchet_generation')->default(0)->comment('Ratchet generation counter');
            
            // Quantum entropy and security
            $table->binary('quantum_entropy')->nullable()->comment('Additional quantum entropy (encrypted)');
            $table->binary('hybrid_key_material')->nullable()->comment('Hybrid key material (encrypted)');
            
            // Security monitoring
            $table->unsignedBigInteger('encrypted_messages')->default(0);
            $table->unsignedBigInteger('decrypted_messages')->default(0);
            $table->unsignedInteger('failed_decryptions')->default(0);
            $table->timestamp('last_activity')->nullable();
            
            // Threat detection
            $table->boolean('suspicious_activity')->default(false);
            $table->enum('threat_level', ['low', 'medium', 'high', 'critical'])->default('low');
            
            // Compliance
            $table->boolean('nist_compliant')->default(true);
            $table->boolean('quantum_ready')->default(true);
            $table->json('audit_trail')->nullable()->comment('Security event audit trail');
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['quantum_epoch', 'quantum_ready']);
            $table->index(['threat_level', 'suspicious_activity']);
            $table->index(['last_activity']);
        });

        // Create quantum secure messages table for cross-device support
        Schema::create('quantum_secure_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique()->index()->comment('Unique message identifier');
            $table->string('conversation_id')->index()->comment('Conversation identifier');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->string('sender_device_id')->index()->comment('Sender device identifier');
            
            // Enhanced encryption layers
            $table->longText('encrypted_content')->comment('Primary encrypted content');
            $table->text('content_nonce')->comment('Content encryption nonce');
            $table->text('content_auth_tag')->comment('Content authentication tag');
            $table->longText('hybrid_ciphertext')->nullable()->comment('Secondary encryption layer');
            $table->text('hybrid_nonce')->nullable()->comment('Hybrid encryption nonce');
            
            // Dual KEM protection
            $table->longText('kem_ciphertext')->comment('ML-KEM-1024 encapsulated key');
            $table->text('kem_public_key')->comment('ML-KEM-1024 public key');
            $table->longText('hybrid_kem_ciphertext')->nullable()->comment('FrodoKEM-1344 encapsulated key');
            $table->text('hybrid_kem_public_key')->nullable()->comment('FrodoKEM-1344 public key');
            
            // Triple signature protection
            $table->longText('message_signature')->comment('Primary ML-DSA-87 signature');
            $table->longText('hybrid_signature')->nullable()->comment('SLH-DSA backup signature');
            $table->longText('backup_signature')->nullable()->comment('Secondary backup signature');
            $table->text('signing_public_key')->comment('Primary signing public key');
            $table->text('hybrid_signing_key')->nullable()->comment('Hybrid signing key');
            
            // Enhanced forward secrecy
            $table->text('ratchet_public_key')->comment('Double ratchet public key');
            $table->text('quantum_ratchet_key')->nullable()->comment('Quantum ratchet key');
            $table->unsignedBigInteger('message_number')->comment('Message sequence number');
            $table->unsignedBigInteger('chain_length')->comment('Chain length for forward secrecy');
            $table->unsignedInteger('ratchet_generation')->default(0)->comment('Ratchet generation');
            
            // Quantum safety metadata
            $table->unsignedInteger('quantum_epoch')->comment('Quantum epoch');
            $table->unsignedTinyInteger('security_level')->default(5)->comment('NIST security level');
            $table->string('algorithm')->default('Quantum-Safe-E2EE-v3.0-MaxSec')->comment('Encryption algorithm');
            
            // Triple integrity protection
            $table->text('message_hash')->comment('SHA-256 message hash');
            $table->text('blake3_hash')->nullable()->comment('BLAKE3 message hash');
            $table->text('sha3_hash')->nullable()->comment('SHA-3 message hash');
            $table->text('timestamp_signature')->comment('Anti-replay timestamp signature');
            $table->text('anti_replay_nonce')->comment('Anti-replay nonce');
            $table->text('quantum_nonce')->nullable()->comment('Additional quantum nonce');
            
            // Advanced quantum proofs
            $table->text('quantum_resistance_proof')->comment('Quantum resistance proof');
            $table->text('zero_knowledge_proof')->nullable()->comment('Zero-knowledge proof');
            $table->text('homomorphic_proof')->nullable()->comment('Homomorphic encryption proof');
            
            // Security metadata
            $table->text('device_fingerprint')->comment('Sender device fingerprint');
            $table->text('quantum_entropy')->nullable()->comment('Quantum entropy source');
            $table->boolean('sidechannel_resistance')->default(true);
            $table->boolean('fault_injection_resistance')->default(true);
            
            // Multi-device support
            $table->json('target_devices')->comment('Target device identifiers');
            $table->json('encrypted_for_devices')->comment('Per-device encrypted data');
            $table->boolean('quantum_safe')->default(true);
            $table->json('encryption_metadata')->comment('Encryption metadata');
            
            $table->timestamp('expires_at')->nullable()->comment('Message expiration');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['conversation_id', 'message_number']);
            $table->index(['sender_device_id', 'created_at']);
            $table->index(['quantum_epoch', 'quantum_safe']);
            $table->index(['expires_at']);
            $table->index(['security_level', 'algorithm']);
        });

        // Create quantum key material storage
        Schema::create('quantum_key_material', function (Blueprint $table) {
            $table->id();
            $table->string('key_id')->unique()->index()->comment('Unique key identifier');
            $table->foreignId('user_device_id')->constrained()->onDelete('cascade');
            $table->string('key_type')->comment('Key type (identity, kem, hybrid, backup)');
            $table->string('algorithm')->comment('Cryptographic algorithm');
            $table->unsignedTinyInteger('security_level')->default(5)->comment('NIST security level');
            
            // Encrypted key data
            $table->longText('public_key_data')->comment('Public key (encrypted at rest)');
            $table->longText('private_key_data')->comment('Private key (encrypted at rest)');
            $table->text('key_derivation_salt')->comment('Key derivation salt');
            
            // Key lifecycle
            $table->timestamp('created_at')->comment('Key creation timestamp');
            $table->timestamp('expires_at')->nullable()->comment('Key expiration');
            $table->timestamp('rotated_at')->nullable()->comment('Last rotation timestamp');
            $table->boolean('is_active')->default(true);
            $table->boolean('hardware_protected')->default(false);
            
            // Quantum properties
            $table->unsignedInteger('quantum_strength')->default(512);
            $table->unsignedInteger('quantum_epoch')->comment('Creation quantum epoch');
            $table->json('quantum_metadata')->nullable()->comment('Quantum-specific metadata');
            
            // Usage tracking
            $table->unsignedBigInteger('usage_count')->default(0)->comment('Key usage counter');
            $table->unsignedInteger('max_usage')->default(1000)->comment('Maximum usage limit');
            $table->timestamp('last_used_at')->nullable();
            
            // Security audit
            $table->json('security_audit')->nullable()->comment('Security audit trail');
            $table->timestamp('updated_at')->nullable();
            
            // Indexes
            $table->index(['user_device_id', 'key_type']);
            $table->index(['algorithm', 'security_level']);
            $table->index(['quantum_epoch', 'is_active']);
            $table->index(['expires_at']);
        });

        // Create quantum security metrics table
        Schema::create('quantum_security_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('assessment_timestamp')->comment('Metrics assessment timestamp');
            
            // Overall security scores
            $table->decimal('overall_security_score', 5, 2)->comment('Overall security score (0-100)');
            $table->decimal('quantum_readiness_score', 5, 2)->comment('Quantum readiness score (0-100)');
            
            // Algorithm strength metrics
            $table->unsignedSmallInteger('kem_strength')->default(256)->comment('KEM security bits');
            $table->unsignedSmallInteger('signature_strength')->default(256)->comment('Signature security bits');
            $table->unsignedSmallInteger('encryption_strength')->default(256)->comment('Encryption security bits');
            
            // Forward secrecy metrics
            $table->boolean('forward_secrecy_active')->default(true);
            $table->decimal('key_rotation_frequency', 8, 2)->comment('Key rotation frequency (minutes)');
            $table->decimal('average_key_lifetime', 10, 2)->comment('Average key lifetime (minutes)');
            
            // Performance metrics
            $table->decimal('average_encryption_time', 8, 2)->nullable()->comment('Avg encryption time (ms)');
            $table->decimal('average_decryption_time', 8, 2)->nullable()->comment('Avg decryption time (ms)');
            $table->decimal('average_key_generation_time', 8, 2)->nullable()->comment('Avg key gen time (ms)');
            
            // Compliance status
            $table->boolean('nist_compliant')->default(true);
            $table->boolean('fips_approved')->default(false);
            $table->boolean('common_criteria_evaluated')->default(false);
            
            // Threat status
            $table->unsignedInteger('active_threats')->default(0);
            $table->unsignedInteger('mitigated_threats')->default(0);
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            
            // Device metrics
            $table->unsignedInteger('total_devices')->default(0);
            $table->unsignedInteger('trusted_devices')->default(0);
            $table->unsignedInteger('active_devices')->default(0);
            $table->decimal('average_trust_level', 4, 2)->default(0.0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'assessment_timestamp']);
            $table->index(['quantum_readiness_score', 'nist_compliant']);
            $table->index(['risk_level', 'active_threats']);
        });

        // Add quantum-safe indexes to existing tables
        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('chat_messages', 'quantum_secure_message_id')) {
                    $table->string('quantum_secure_message_id')->nullable()->index()->comment('Reference to quantum secure message');
                    $table->unsignedInteger('quantum_epoch')->nullable()->index()->comment('Message quantum epoch');
                    $table->boolean('quantum_verified')->default(false)->comment('Quantum verification status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new quantum tables
        Schema::dropIfExists('quantum_security_metrics');
        Schema::dropIfExists('quantum_key_material');
        Schema::dropIfExists('quantum_secure_messages');
        Schema::dropIfExists('quantum_conversation_states');
        
        // Revert chat_messages changes
        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropColumn([
                    'quantum_secure_message_id',
                    'quantum_epoch',
                    'quantum_verified'
                ]);
            });
        }
        
        // Revert user_devices changes (WARNING: This will lose quantum key data)
        Schema::table('user_devices', function (Blueprint $table) {
            // Drop quantum-safe columns
            $table->dropColumn([
                'identity_public_key',
                'kem_public_key',
                'hybrid_kem_public_key',
                'backup_public_key',
                'secondary_backup_public_key',
                'quantum_strength',
                'sidechannel_resistant',
                'fault_injection_resistant',
                'quantum_implementation',
                'quantum_algorithms',
                'quantum_epoch',
                'ratchet_generation',
                'last_quantum_rotation',
                'threat_metrics',
                'failed_decryptions',
                'suspicious_activity',
                'threat_level',
                'nist_compliant',
                'fips_approved',
                'common_criteria_evaluated',
                'avg_encryption_time',
                'avg_decryption_time',
                'avg_key_generation_time',
                'security_audit_trail'
            ]);
            
            // Restore old public key structure
            $table->json('public_key')->nullable()->comment('Legacy public key structure');
        });
    }
};