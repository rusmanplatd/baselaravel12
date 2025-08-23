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
        Schema::table('organization_positions', function (Blueprint $table) {
            // Add organization_id if it doesn't exist
            if (! Schema::hasColumn('organization_positions', 'organization_id')) {
                $table->ulid('organization_id')->after('id');
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            }
        });

        // Update existing records to set organization_id based on organization_unit
        DB::statement('
            UPDATE organization_positions 
            SET organization_id = (
                SELECT organization_id 
                FROM organization_units 
                WHERE organization_units.id = organization_positions.organization_unit_id
            )
            WHERE organization_id IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_positions', function (Blueprint $table) {
            if (Schema::hasColumn('organization_positions', 'organization_id')) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            }
        });
    }
};