<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add scoping support to permissions table
        Schema::table('sys_permissions', function (Blueprint $table) {
            $table->string('scope_type')->nullable()->after('guard_name')->comment('Type of resource this permission is scoped to (organization, chat, project, etc.)');
            $table->ulid('scope_id')->nullable()->after('scope_type')->comment('ID of the resource this permission is scoped to');
            $table->boolean('is_global')->default(false)->after('scope_id')->comment('Whether this is a global permission (not scoped to any resource)');
            $table->json('scope_path')->nullable()->after('is_global')->comment('JSON array representing the scope hierarchy path');
            
            // Add indexes for performance
            $table->index(['scope_type', 'scope_id'], 'permissions_scope_index');
            $table->index('is_global', 'permissions_global_index');
            $table->index('scope_type', 'permissions_scope_type_index');
        });

        // Add scoping support to roles table  
        Schema::table('sys_roles', function (Blueprint $table) {
            $table->string('scope_type')->nullable()->after('team_id')->comment('Type of resource this role is scoped to');
            $table->ulid('scope_id')->nullable()->after('scope_type')->comment('ID of the resource this role is scoped to');
            $table->boolean('is_global')->default(false)->after('scope_id')->comment('Whether this is a global role (not scoped to any resource)');
            $table->json('scope_path')->nullable()->after('is_global')->comment('JSON array representing the scope hierarchy path');
            $table->smallInteger('type')->default(1)->change()->comment('Role type: 1=standard, 2=system, 3=custom');
            
            // Add indexes for performance
            $table->index(['scope_type', 'scope_id'], 'roles_scope_index');
            $table->index('is_global', 'roles_global_index');
            $table->index('scope_type', 'roles_scope_type_index');
        });

        // Add scoping support to model_has_permissions pivot table
        Schema::table('sys_model_has_permissions', function (Blueprint $table) {
            $table->string('scope_type')->nullable()->after('team_id')->comment('Type of resource this permission assignment is scoped to');
            $table->ulid('scope_id')->nullable()->after('scope_type')->comment('ID of the resource this permission assignment is scoped to');
            $table->json('scope_path')->nullable()->after('scope_id')->comment('JSON array representing the scope hierarchy path');
            
            // Add indexes for performance
            $table->index(['scope_type', 'scope_id'], 'model_has_permissions_scope_index');
        });

        // Add scoping support to model_has_roles pivot table
        Schema::table('sys_model_has_roles', function (Blueprint $table) {
            $table->string('scope_type')->nullable()->after('team_id')->comment('Type of resource this role assignment is scoped to');
            $table->ulid('scope_id')->nullable()->after('scope_type')->comment('ID of the resource this role assignment is scoped to');
            $table->json('scope_path')->nullable()->after('scope_id')->comment('JSON array representing the scope hierarchy path');
            
            // Add indexes for performance
            $table->index(['scope_type', 'scope_id'], 'model_has_roles_scope_index');
        });

        // Create permission scopes table to track scope relationships and inheritance
        Schema::create('sys_permission_scopes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('scope_type')->comment('Type of the scoped resource (organization, chat, project, etc.)');
            $table->ulid('scope_id')->comment('ID of the scoped resource');
            $table->string('parent_scope_type')->nullable()->comment('Type of parent scope for inheritance');
            $table->ulid('parent_scope_id')->nullable()->comment('ID of parent scope for inheritance');
            $table->json('scope_path')->nullable()->comment('Full path from root to this scope');
            $table->boolean('inherits_permissions')->default(true)->comment('Whether this scope inherits permissions from parent');
            $table->json('metadata')->nullable()->comment('Additional scope metadata');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();

            // Indexes
            $table->unique(['scope_type', 'scope_id'], 'permission_scopes_unique');
            $table->index(['parent_scope_type', 'parent_scope_id'], 'permission_scopes_parent_index');
            $table->index('scope_type', 'permission_scopes_type_index');
            
            // Foreign keys
            $table->foreign('created_by')->references('id')->on('sys_users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('sys_users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_permission_scopes');

        Schema::table('sys_model_has_roles', function (Blueprint $table) {
            $table->dropIndex('model_has_roles_scope_index');
            $table->dropColumn(['scope_type', 'scope_id', 'scope_path']);
        });

        Schema::table('sys_model_has_permissions', function (Blueprint $table) {
            $table->dropIndex('model_has_permissions_scope_index');
            $table->dropColumn(['scope_type', 'scope_id', 'scope_path']);
        });

        Schema::table('sys_roles', function (Blueprint $table) {
            $table->dropIndex(['roles_scope_index', 'roles_global_index', 'roles_scope_type_index']);
            $table->dropColumn(['scope_type', 'scope_id', 'is_global', 'scope_path']);
        });

        Schema::table('sys_permissions', function (Blueprint $table) {
            $table->dropIndex(['permissions_scope_index', 'permissions_global_index', 'permissions_scope_type_index']);
            $table->dropColumn(['scope_type', 'scope_id', 'is_global', 'scope_path']);
        });
    }
};