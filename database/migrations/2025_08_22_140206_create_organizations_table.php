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
        Schema::create('organizations', function (Blueprint $table) {
            $table->ulid('id');
            $table->string('organization_code')->unique()->nullable();
            $table->enum('organization_type', [
                'holding_company',
                'subsidiary',
                'division',
                'branch',
                'department',
                'unit'
            ])->default('subsidiary');
            $table->ulid('parent_organization_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('registration_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->json('governance_structure')->nullable();
            $table->decimal('authorized_capital', 15, 2)->nullable();
            $table->decimal('paid_capital', 15, 2)->nullable();
            $table->date('establishment_date')->nullable();
            $table->string('legal_status')->nullable();
            $table->text('business_activities')->nullable();
            $table->json('contact_persons')->nullable();
            $table->integer('level')->default(0);
            $table->string('path')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->index();

            $table->foreign('created_by')->references('id')->on('sys_users');
            $table->foreign('updated_by')->references('id')->on('sys_users');

            $table->primary('id');
            $table->foreign('parent_organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('organization_units', function (Blueprint $table) {
            $table->ulid('id');
            $table->ulid('organization_id');
            $table->string('unit_code')->unique();
            $table->string('name');
            $table->enum('unit_type', [
                'board_of_commissioners',
                'board_of_directors',
                'executive_committee',
                'audit_committee',
                'risk_committee',
                'nomination_committee',
                'remuneration_committee',
                'division',
                'department',
                'section',
                'team',
                'branch_office',
                'representative_office'
            ]);
            $table->text('description')->nullable();
            $table->ulid('parent_unit_id')->nullable();
            $table->json('responsibilities')->nullable();
            $table->json('authorities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->index();

            $table->foreign('created_by')->references('id')->on('sys_users');
            $table->foreign('updated_by')->references('id')->on('sys_users');

            $table->primary('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('parent_unit_id')->references('id')->on('organization_units')->onDelete('cascade');
        });

        Schema::create('organization_position_levels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('hierarchy_level')->comment('Lower numbers = higher hierarchy');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->index();
        });

        Schema::create('organization_positions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_unit_id');
            $table->string('position_code')->unique();
            $table->ulid('organization_position_level_id');
            $table->string('title');
            $table->text('job_description')->nullable();
            $table->json('qualifications')->nullable();
            $table->json('responsibilities')->nullable();
            $table->decimal('min_salary', 12, 2)->nullable();
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('max_incumbents')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->index();

            $table->foreign('created_by')->references('id')->on('sys_users');
            $table->foreign('updated_by')->references('id')->on('sys_users');
            $table->foreign('organization_position_level_id')->references('id')->on('organization_position_levels');

            $table->foreign('organization_unit_id')->references('id')->on('organization_units')->onDelete('cascade');
        });

        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('organization_id');
            $table->ulid('organization_unit_id')->nullable();
            $table->ulid('organization_position_id')->nullable();
            $table->enum('membership_type', [
                'employee',
                'board_member',
                'consultant',
                'contractor',
                'intern'
            ])->default('employee');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'terminated'])->default('active');
            $table->json('additional_roles')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->index();

            $table->foreign('user_id')->references('id')->on('sys_users');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('organization_unit_id')->references('id')->on('organization_units');
            $table->foreign('organization_position_id')->references('id')->on('organization_positions');

            $table->foreign('created_by')->references('id')->on('sys_users');
            $table->foreign('updated_by')->references('id')->on('sys_users');

            $table->unique(['user_id', 'organization_id', 'organization_position_id'], 'unique_user_org_position');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organization_positions');
        Schema::dropIfExists('organization_position_levels');
        Schema::dropIfExists('organization_units');
        Schema::dropIfExists('organizations');
    }
};
