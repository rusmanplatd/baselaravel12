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
            $table->string('street_address')->nullable()->after('public_key');
            $table->string('locality')->nullable()->after('street_address');
            $table->string('region')->nullable()->after('locality');
            $table->string('postal_code')->nullable()->after('region');
            $table->string('country')->nullable()->after('postal_code');
            $table->text('formatted_address')->nullable()->after('country');
            $table->string('phone_number')->nullable()->after('formatted_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropColumn([
                'street_address',
                'locality',
                'region',
                'postal_code',
                'country',
                'formatted_address',
                'phone_number',
            ]);
        });
    }
};
