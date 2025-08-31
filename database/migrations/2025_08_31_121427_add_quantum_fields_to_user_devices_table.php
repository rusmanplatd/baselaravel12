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
            // Quantum-specific fields for capability management and health monitoring
            $table->string('preferred_algorithm')->nullable()->after('device_capabilities');
            $table->integer('quantum_health_score')->default(100)->after('preferred_algorithm');
            $table->timestamp('capabilities_verified_at')->nullable()->after('quantum_health_score');
            $table->timestamp('last_quantum_health_check')->nullable()->after('capabilities_verified_at');
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
                'quantum_health_score',
                'capabilities_verified_at',
                'last_quantum_health_check',
            ]);
        });
    }
};
