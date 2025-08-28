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
        // Drop the existing check constraint and recreate with the new value
        DB::statement('ALTER TABLE user_devices DROP CONSTRAINT user_devices_security_level_check');
        DB::statement("ALTER TABLE user_devices ADD CONSTRAINT user_devices_security_level_check CHECK (security_level::text = ANY (ARRAY['low'::character varying, 'medium'::character varying, 'high'::character varying, 'maximum'::character varying, 'compromised'::character varying]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: PostgreSQL doesn't support removing enum values, so this will need manual intervention
        // This is a limitation of PostgreSQL enum types
    }
};
