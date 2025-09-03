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
            // Add revocation fields
            $table->timestamp('revoked_at')->nullable()->after('is_active');
            $table->string('revocation_reason')->nullable()->after('revoked_at');
            
            // Add indexes for performance
            $table->index('revoked_at');
            $table->index(['is_active', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_encryption_keys', function (Blueprint $table) {
            $table->dropIndex(['revoked_at']);
            $table->dropIndex(['is_active', 'revoked_at']);
            $table->dropColumn(['revoked_at', 'revocation_reason']);
        });
    }
};