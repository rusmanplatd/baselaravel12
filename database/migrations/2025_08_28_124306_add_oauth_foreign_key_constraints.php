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
        // Add foreign key constraints to OAuth tables referencing oauth_clients
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
        });

        Schema::table('oauth_device_codes', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::table('oauth_device_codes', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
    }
};
