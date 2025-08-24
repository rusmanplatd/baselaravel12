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
        Schema::table('chat_encryption_keys', function (Blueprint $table) {
            // Add new columns for enhanced multi-device encryption
            $table->string('algorithm', 50)->default('RSA-4096-OAEP')->after('key_version');
            $table->integer('key_strength')->default(4096)->after('algorithm');
            $table->timestamp('last_used_at')->nullable()->after('key_strength');
            $table->json('device_metadata')->nullable()->after('last_used_at');

            // Make device_id and device_fingerprint required (breaking change)
            $table->string('device_id')->nullable(false)->change();
            $table->string('device_fingerprint')->nullable(false)->change();

            // Add index for better performance
            $table->index(['user_id', 'device_id', 'is_active'], 'idx_user_device_active');
            $table->index(['conversation_id', 'key_version', 'is_active'], 'idx_conversation_version_active');
            $table->index(['device_fingerprint', 'is_active'], 'idx_fingerprint_active');
        });

        // Update existing records to have required values
        DB::statement("
            UPDATE chat_encryption_keys
            SET device_fingerprint = COALESCE(device_fingerprint, CONCAT('legacy-', id))
            WHERE device_fingerprint IS NULL
        ");

        // Create legacy device entries for existing keys without device_id
        DB::statement("
            INSERT INTO user_devices (id, user_id, device_name, device_type, device_fingerprint, platform, is_trusted, is_active, created_at, updated_at, last_used_at)
            SELECT
                CONCAT('legacy-device-', ek.user_id) as id,
                ek.user_id,
                'Legacy Device' as device_name,
                'web' as device_type,
                CONCAT('legacy-', ek.user_id) as device_fingerprint,
                'web' as platform,
                true as is_trusted,
                false as is_active,
                MIN(ek.created_at) as created_at,
                NOW() as updated_at,
                MAX(ek.updated_at) as last_used_at
            FROM chat_encryption_keys ek
            WHERE ek.device_id IS NULL
            AND NOT EXISTS (
                SELECT 1 FROM user_devices ud
                WHERE ud.device_fingerprint = CONCAT('legacy-', ek.user_id)
            )
            GROUP BY ek.user_id
            ON CONFLICT (device_fingerprint) DO NOTHING
        ");

        // Update existing keys without device_id to use legacy device
        DB::statement("
            UPDATE chat_encryption_keys ek
            SET device_id = (
                SELECT ud.id
                FROM user_devices ud
                WHERE ud.user_id = ek.user_id
                AND ud.device_fingerprint = CONCAT('legacy-', ek.user_id)
                LIMIT 1
            )
            WHERE ek.device_id IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_encryption_keys', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('idx_user_device_active');
            $table->dropIndex('idx_conversation_version_active');
            $table->dropIndex('idx_fingerprint_active');

            // Remove new columns
            $table->dropColumn([
                'algorithm',
                'key_strength',
                'last_used_at',
                'device_metadata',
            ]);

            // Make device fields nullable again
            $table->string('device_id')->nullable()->change();
            $table->string('device_fingerprint')->nullable()->change();
        });

        // Remove legacy devices
        DB::statement("DELETE FROM user_devices WHERE device_fingerprint LIKE 'legacy-%'");
    }
};
