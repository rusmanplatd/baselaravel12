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
        DB::statement('DROP INDEX IF EXISTS unique_active_conversation_device');
        
        // Create a new unique constraint that includes algorithm
        // This allows multiple active keys per device if they use different algorithms
        DB::statement('CREATE UNIQUE INDEX unique_active_conversation_device_algorithm ON chat_encryption_keys (conversation_id, device_id, algorithm) WHERE is_active = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new constraint
        DB::statement('DROP INDEX IF EXISTS unique_active_conversation_device_algorithm');
        
        // Restore the old constraint
        DB::statement('CREATE UNIQUE INDEX unique_active_conversation_device ON chat_encryption_keys (conversation_id, device_id) WHERE is_active = true');
    }
};
