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
        Schema::table('oauth_clients', function (Blueprint $table) {
            // Add user_access_scope field to control who can access this OAuth client
            // Options: 'all_users', 'organization_members', 'custom'
            $table->enum('user_access_scope', ['all_users', 'organization_members', 'custom'])
                ->nullable(false)
                ->after('client_type')
                ->comment('Controls which users can access this OAuth client');
            
            // JSON field to store custom user access rules when user_access_scope is 'custom'
            $table->json('user_access_rules')->nullable()->after('user_access_scope')
                ->comment('Custom rules for user access when user_access_scope is custom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['user_access_scope', 'user_access_rules']);
        });
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};