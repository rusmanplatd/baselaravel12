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
        Schema::table('user_consents', function (Blueprint $table) {
            $table->json('scope_details')->after('scopes');
            $table->timestamp('last_used_at')->nullable()->after('scope_details');
            $table->timestamp('expires_at')->nullable()->after('last_used_at');
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active')->after('expires_at');
            $table->string('granted_by_ip')->nullable()->after('status');
            $table->text('granted_user_agent')->nullable()->after('granted_by_ip');
            $table->json('usage_stats')->nullable()->after('granted_user_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_consents', function (Blueprint $table) {
            $table->dropColumn([
                'scope_details',
                'last_used_at',
                'expires_at',
                'status',
                'granted_by_ip',
                'granted_user_agent',
                'usage_stats',
            ]);
        });
    }
};
