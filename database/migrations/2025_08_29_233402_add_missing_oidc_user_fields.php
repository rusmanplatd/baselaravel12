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
        Schema::table('sys_users', function (Blueprint $table) {
            // Standard OIDC profile claims
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('nickname')->nullable()->after('username');
            $table->string('profile_url')->nullable()->after('nickname');
            $table->string('website')->nullable()->after('profile_url');
            $table->string('gender')->nullable()->after('website');
            $table->date('birthdate')->nullable()->after('gender');
            $table->string('zoneinfo')->nullable()->after('birthdate');
            $table->string('locale')->nullable()->after('zoneinfo');

            // Additional useful fields
            $table->timestamp('phone_verified_at')->nullable()->after('phone_number');
            $table->timestamp('profile_updated_at')->nullable()->after('locale');

            // Social/external identifiers
            $table->string('external_id')->nullable()->unique()->after('profile_updated_at');
            $table->json('social_links')->nullable()->after('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropColumn([
                'middle_name',
                'nickname',
                'profile_url',
                'website',
                'gender',
                'birthdate',
                'zoneinfo',
                'locale',
                'phone_verified_at',
                'profile_updated_at',
                'external_id',
                'social_links',
            ]);
        });
    }
};
