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
            // Add quantum-specific fields
            $table->json('encryption_capabilities')->nullable()->after('device_capabilities');
            $table->boolean('quantum_ready')->default(false)->after('encryption_capabilities');
            
            // Add indexes for performance
            $table->index('quantum_ready');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropIndex(['quantum_ready']);
            $table->dropColumn(['encryption_capabilities', 'quantum_ready']);
        });
    }
};