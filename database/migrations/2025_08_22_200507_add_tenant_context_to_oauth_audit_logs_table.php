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
        Schema::table('oauth_audit_logs', function (Blueprint $table) {
            $table->ulid('organization_id')->nullable()->after('client_id');
            $table->ulid('tenant_id')->nullable()->after('organization_id');
            $table->string('tenant_domain')->nullable()->after('tenant_id');
            $table->json('organization_context')->nullable()->after('metadata');

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id', 'event_type', 'created_at']);
            $table->index(['tenant_id', 'event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'event_type', 'created_at']);
            $table->dropIndex(['tenant_id', 'event_type', 'created_at']);
            $table->dropColumn(['organization_id', 'tenant_id', 'tenant_domain', 'organization_context']);
        });
    }
};
