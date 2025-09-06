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
            // Core device identification
            $table->string('device_fingerprint')->after('device_type');
            $table->string('hardware_fingerprint')->nullable()->after('device_fingerprint');

            // Device platform and user agent info
            $table->string('platform', 100)->nullable()->after('hardware_fingerprint');
            $table->string('user_agent', 500)->nullable()->after('platform');

            // Encryption and security
            $table->text('public_key')->after('user_agent'); // E2EE public key
            $table->json('device_capabilities')->nullable()->after('public_key'); // Device features
            $table->json('encryption_capabilities')->nullable()->after('device_capabilities'); // Supported encryption
            $table->boolean('quantum_ready')->default(false)->after('encryption_capabilities');
            $table->string('security_level', 50)->default('standard')->after('quantum_ready');

            // Update existing columns
            $table->text('public_identity_key')->nullable()->change(); // Make nullable since we have public_key now
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            // Remove added columns in reverse order
            $table->dropColumn([
                'security_level',
                'quantum_ready',
                'encryption_capabilities',
                'device_capabilities',
                'public_key',
                'user_agent',
                'platform',
                'hardware_fingerprint',
                'device_fingerprint'
            ]);

            // Revert public_identity_key to not nullable
            $table->text('public_identity_key')->nullable(false)->change();
        });
    }
};
