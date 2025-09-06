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
        Schema::table('user_devices', function (Blueprint $table) {
            // Encryption and security tracking
            $table->integer('encryption_version')->default(2)->after('quantum_ready');
            $table->integer('failed_auth_attempts')->default(0)->after('encryption_version');
            $table->integer('quantum_health_score')->default(100)->after('failed_auth_attempts');
            
            // Trust and verification management
            $table->string('trust_level', 50)->nullable()->after('quantum_health_score');
            $table->timestamp('verified_at')->nullable()->after('trust_level');
            $table->timestamp('auto_trust_expires_at')->nullable()->after('verified_at');
            
            // Security and maintenance timestamps
            $table->timestamp('last_key_rotation_at')->nullable()->after('auto_trust_expires_at');
            $table->timestamp('capabilities_verified_at')->nullable()->after('last_key_rotation_at');
            $table->timestamp('last_quantum_health_check')->nullable()->after('capabilities_verified_at');
            
            // Security lockout and revocation
            $table->timestamp('locked_until')->nullable()->after('last_quantum_health_check');
            $table->timestamp('revoked_at')->nullable()->after('locked_until');
            $table->text('revocation_reason')->nullable()->after('revoked_at');
            
            // Algorithm preferences
            $table->string('preferred_algorithm', 100)->nullable()->after('revocation_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_algorithm',
                'revocation_reason', 
                'revoked_at',
                'locked_until',
                'last_quantum_health_check',
                'capabilities_verified_at',
                'last_key_rotation_at',
                'auto_trust_expires_at',
                'verified_at',
                'trust_level',
                'quantum_health_score',
                'failed_auth_attempts',
                'encryption_version'
            ]);
        });
    }
};
