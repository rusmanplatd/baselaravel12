<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing check constraint and add a new one with 'manager'
        DB::statement("ALTER TABLE organization_memberships DROP CONSTRAINT organization_memberships_membership_type_check");
        DB::statement("ALTER TABLE organization_memberships ADD CONSTRAINT organization_memberships_membership_type_check CHECK (membership_type::text = ANY (ARRAY['employee'::character varying, 'board_member'::character varying, 'consultant'::character varying, 'contractor'::character varying, 'intern'::character varying, 'manager'::character varying]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'manager' from the membership_type check constraint
        DB::statement("ALTER TABLE organization_memberships DROP CONSTRAINT organization_memberships_membership_type_check");
        DB::statement("ALTER TABLE organization_memberships ADD CONSTRAINT organization_memberships_membership_type_check CHECK (membership_type::text = ANY (ARRAY['employee'::character varying, 'board_member'::character varying, 'consultant'::character varying, 'contractor'::character varying, 'intern'::character varying]::text[]))");
    }
};
