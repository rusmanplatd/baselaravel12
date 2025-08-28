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
        Schema::table('sessions', function (Blueprint $table) {
            // Add additional columns to extend Laravel's built-in sessions table
            $table->string('browser')->nullable()->after('user_agent');
            $table->string('platform')->nullable()->after('browser');
            $table->string('device_type')->nullable()->after('platform');
            $table->string('location')->nullable()->after('device_type');
            $table->timestamp('login_at')->nullable()->after('last_activity');
            $table->boolean('is_active')->default(true)->after('login_at');
            $table->json('metadata')->nullable()->after('is_active');

            // Add indexes for performance
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropColumn([
                'browser',
                'platform',
                'device_type',
                'location',
                'login_at',
                'is_active',
                'metadata',
            ]);
        });
    }
};
