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
        Schema::table('message_reactions', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['device_id']);
            
            // Make device_id nullable
            $table->foreignUlid('device_id')->nullable()->change();
            
            // Re-add the foreign key constraint allowing null
            $table->foreign('device_id')->references('id')->on('user_devices')->onDelete('cascade');
            
            // Update the unique constraint to handle nullable device_id
            $table->dropUnique(['message_id', 'user_id', 'device_id', 'reaction_type']);
            $table->unique(['message_id', 'user_id', 'reaction_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_reactions', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropUnique(['message_id', 'user_id', 'reaction_type']);
            
            $table->foreignUlid('device_id')->nullable(false)->change();
            $table->foreign('device_id')->references('id')->on('user_devices')->onDelete('cascade');
            $table->unique(['message_id', 'user_id', 'device_id', 'reaction_type']);
        });
    }
};
