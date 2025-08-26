<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove any clients without organization_id (legacy clients)
        DB::table('oauth_clients')
          ->whereNull('organization_id')
          ->delete();

        // Set default user_access_scope for any clients that don't have it
        DB::table('oauth_clients')
          ->whereNull('user_access_scope')
          ->update(['user_access_scope' => 'organization_members']);

        // Make organization_id required (remove nullable)
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->ulid('organization_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->ulid('organization_id')->nullable()->change();
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