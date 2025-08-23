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
            $table->ulid('organization_id')->nullable()->after('user_id');
            $table->json('allowed_scopes')->nullable()->after('policy_uri');
            $table->string('client_type')->default('public')->after('revoked');
            $table->timestamp('last_used_at')->nullable()->after('updated_at');

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'revoked']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'revoked']);
            $table->dropColumn(['organization_id', 'allowed_scopes', 'client_type', 'last_used_at']);
        });
    }
};
