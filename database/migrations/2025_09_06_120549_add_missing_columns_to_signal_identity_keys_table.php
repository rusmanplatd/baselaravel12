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
        Schema::table('signal_identity_keys', function (Blueprint $table) {
            // Add missing columns expected by IdentityKey model
            $table->integer('registration_id')->after('user_id');
            $table->boolean('is_active')->default(true)->after('key_fingerprint');
            
            // Quantum cryptography support
            $table->text('quantum_public_key')->nullable()->after('is_active');
            $table->text('quantum_private_key_encrypted')->nullable()->after('quantum_public_key');
            $table->string('quantum_algorithm')->nullable()->after('quantum_private_key_encrypted');
            $table->boolean('is_quantum_capable')->default(false)->after('quantum_algorithm');
            $table->integer('quantum_version')->default(1)->after('is_quantum_capable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signal_identity_keys', function (Blueprint $table) {
            $table->dropColumn([
                'quantum_version',
                'is_quantum_capable',
                'quantum_algorithm',
                'quantum_private_key_encrypted',
                'quantum_public_key',
                'is_active',
                'registration_id'
            ]);
        });
    }
};
