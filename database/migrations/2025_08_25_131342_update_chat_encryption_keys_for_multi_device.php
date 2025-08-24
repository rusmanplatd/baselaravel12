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
            // Drop the unique constraint that prevents multiple devices
            $table->dropUnique(['conversation_id', 'user_id']);

            // Add device_id to link keys to specific devices
            $table->ulid('device_id')->nullable()->after('user_id');
            $table->foreign('device_id')->references('id')->on('user_devices')->cascadeOnDelete();

            // Add device fingerprint for quick device identification
            $table->string('device_fingerprint')->nullable()->after('device_id');

            // Add key version for key rotation tracking
            $table->integer('key_version')->default(1)->after('public_key');

            // Add created_by_device to track which device created this key
            $table->ulid('created_by_device_id')->nullable()->after('key_version');
            $table->foreign('created_by_device_id')->references('id')->on('user_devices')->nullOnDelete();

            // New unique constraint: one key per conversation per device
            $table->unique(['conversation_id', 'device_id'], 'unique_conversation_device');

            // Add indexes for multi-device queries
            $table->index(['user_id', 'device_id', 'is_active']);
            $table->index(['device_fingerprint', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_encryption_keys', function (Blueprint $table) {
            // Drop new constraints and indexes
            $table->dropIndex('unique_conversation_device');
            $table->dropIndex(['user_id', 'device_id', 'is_active']);
            $table->dropIndex(['device_fingerprint', 'is_active']);

            // Drop foreign key constraints
            $table->dropForeign(['device_id']);
            $table->dropForeign(['created_by_device_id']);

            // Drop new columns
            $table->dropColumn(['device_id', 'device_fingerprint', 'key_version', 'created_by_device_id']);

            // Restore original unique constraint
            $table->unique(['conversation_id', 'user_id']);
        });
    }
};
