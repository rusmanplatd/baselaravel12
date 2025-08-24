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
            // Enhanced device capabilities and security
            $table->json('device_capabilities')->nullable()->after('user_agent');
            $table->enum('security_level', ['low', 'medium', 'high', 'maximum'])->default('medium')->after('device_capabilities');
            $table->integer('encryption_version')->default(2)->after('security_level');
            $table->timestamp('auto_trust_expires_at')->nullable()->after('verified_at');

            // Additional tracking fields
            $table->string('hardware_fingerprint')->nullable()->after('device_fingerprint');
            $table->json('device_info')->nullable()->after('hardware_fingerprint');
            $table->timestamp('last_key_rotation_at')->nullable()->after('last_used_at');
            $table->integer('failed_auth_attempts')->default(0)->after('last_key_rotation_at');
            $table->timestamp('locked_until')->nullable()->after('failed_auth_attempts');

            // Indexes for better performance
            $table->index(['user_id', 'is_trusted', 'is_active'], 'idx_user_trusted_active');
            $table->index(['device_fingerprint'], 'idx_device_fingerprint');
            $table->index(['hardware_fingerprint'], 'idx_hardware_fingerprint');
            $table->index(['security_level', 'is_active'], 'idx_security_active');
        });

        // Update existing devices with default capabilities
        DB::statement("
            UPDATE user_devices
            SET device_capabilities = JSON_ARRAY('messaging', 'encryption')
            WHERE device_capabilities IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('idx_user_trusted_active');
            $table->dropIndex('idx_device_fingerprint');
            $table->dropIndex('idx_hardware_fingerprint');
            $table->dropIndex('idx_security_active');

            // Remove new columns
            $table->dropColumn([
                'device_capabilities',
                'security_level',
                'encryption_version',
                'auto_trust_expires_at',
                'hardware_fingerprint',
                'device_info',
                'last_key_rotation_at',
                'failed_auth_attempts',
                'locked_until',
            ]);
        });
    }
};
