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
            // Drop the existing global unique constraint
            $table->dropUnique(['device_fingerprint']);
            
            // Add a composite unique constraint for user_id + device_fingerprint
            // This allows the same device fingerprint to exist for different users
            $table->unique(['user_id', 'device_fingerprint'], 'user_devices_user_fingerprint_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('user_devices_user_fingerprint_unique');
            
            // Restore the global unique constraint
            $table->unique('device_fingerprint');
        });
    }
};