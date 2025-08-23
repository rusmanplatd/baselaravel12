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
        // Add organization_id to organization_positions if it doesn't exist
        if (Schema::hasTable('organization_positions') && ! Schema::hasColumn('organization_positions', 'organization_id')) {
            Schema::table('organization_positions', function (Blueprint $table) {
                $table->ulid('organization_id')->after('id')->nullable();
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
                $table->index('organization_id');
            });

            // Only populate if there are existing records
            $existingPositions = DB::table('organization_positions')->count();
            if ($existingPositions > 0) {
                // Populate organization_id from organization_unit relationship
                DB::statement('
                    UPDATE organization_positions 
                    SET organization_id = (
                        SELECT organization_id 
                        FROM organization_units 
                        WHERE organization_units.id = organization_positions.organization_unit_id
                    )
                    WHERE organization_positions.organization_unit_id IS NOT NULL
                ');

                // Make organization_id not nullable after population
                Schema::table('organization_positions', function (Blueprint $table) {
                    $table->ulid('organization_id')->nullable(false)->change();
                });
            }
        }

        // Ensure activity_log has tenant_id column for custom tenant isolation
        if (Schema::hasTable('activity_log') && ! Schema::hasColumn('activity_log', 'tenant_id')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->ulid('tenant_id')->nullable()->after('organization_id');
                $table->index('tenant_id');
            });
        }

        // Add indexes to improve tenant-scoped queries
        if (Schema::hasTable('organization_units')) {
            Schema::table('organization_units', function (Blueprint $table) {
                if (! $this->hasIndex('organization_units', 'organization_units_organization_id_index')) {
                    $table->index('organization_id');
                }
            });
        }

        if (Schema::hasTable('organization_memberships')) {
            Schema::table('organization_memberships', function (Blueprint $table) {
                if (! $this->hasIndex('organization_memberships', 'organization_memberships_organization_id_index')) {
                    $table->index('organization_id');
                }
                if (! $this->hasIndex('organization_memberships', 'organization_memberships_status_organization_id_index')) {
                    $table->index(['status', 'organization_id']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('organization_positions', 'organization_id')) {
            Schema::table('organization_positions', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }

        if (Schema::hasColumn('activity_log', 'tenant_id')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        // PostgreSQL compatible index check
        $indexes = collect(DB::select("
            SELECT indexname as key_name 
            FROM pg_indexes 
            WHERE tablename = ? 
            AND schemaname = 'public'
        ", [$table]))
            ->pluck('key_name')
            ->toArray();

        return in_array($indexName, $indexes);
    }
};
