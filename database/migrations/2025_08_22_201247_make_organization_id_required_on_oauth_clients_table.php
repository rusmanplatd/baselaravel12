<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, remove any clients without organization_id (legacy cleanup)
        DB::table('oauth_clients')->whereNull('organization_id')->delete();

        // Remove related tokens and codes for orphaned clients
        DB::statement('DELETE FROM oauth_access_tokens WHERE client_id NOT IN (SELECT id FROM oauth_clients)');
        DB::statement('DELETE FROM oauth_refresh_tokens WHERE access_token_id NOT IN (SELECT id FROM oauth_access_tokens)');
        DB::statement('DELETE FROM oauth_auth_codes WHERE client_id NOT IN (SELECT id FROM oauth_clients)');

        // Make client_type required with default
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->string('client_type')->default('confidential')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->ulid('organization_id')->nullable()->change();
            $table->string('client_type')->nullable()->change();
        });
    }
};
