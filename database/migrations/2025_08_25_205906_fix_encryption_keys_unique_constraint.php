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
        // Drop the existing unique constraint
        Schema::table('chat_encryption_keys', function (Blueprint $table) {
            $table->dropUnique('unique_conversation_device');
        });

        // Create a partial unique index that only applies to active keys
        // This allows multiple inactive keys but only one active key per conversation/device
        DB::statement('CREATE UNIQUE INDEX unique_active_conversation_device ON chat_encryption_keys (conversation_id, device_id) WHERE is_active = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the partial unique index
        DB::statement('DROP INDEX IF EXISTS unique_active_conversation_device');

        // Restore the original unique constraint
        Schema::table('chat_encryption_keys', function (Blueprint $table) {
            $table->unique(['conversation_id', 'device_id'], 'unique_conversation_device');
        });
    }
};
