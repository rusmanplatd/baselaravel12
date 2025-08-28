<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->ulid('organization_id')->nullable()->after('batch_ulid');
            $table->string('tenant_id')->nullable()->after('organization_id');

            $table->index(['organization_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['log_name', 'organization_id']);

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropIndex(['log_name', 'organization_id']);
            $table->dropColumn(['organization_id', 'tenant_id']);
        });
    }
};
