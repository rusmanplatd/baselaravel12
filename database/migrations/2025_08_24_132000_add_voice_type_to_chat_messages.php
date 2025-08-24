<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the check constraint to include 'voice' type
        DB::statement("
            ALTER TABLE chat_messages 
            DROP CONSTRAINT IF EXISTS chat_messages_type_check,
            ADD CONSTRAINT chat_messages_type_check 
            CHECK (type IN ('text', 'image', 'file', 'voice', 'system'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot easily remove enum values in PostgreSQL without recreating the type
        // This is acceptable for development/testing
    }
};
