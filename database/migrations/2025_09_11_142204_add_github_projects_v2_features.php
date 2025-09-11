<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create iterations table for sprints/iterations
        Schema::create('project_iterations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['planned', 'active', 'completed', 'cancelled'])->default('planned');
            $table->integer('duration_weeks')->default(2);
            $table->json('goals')->nullable(); // Iteration goals
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'start_date', 'end_date']);
        });

        // Add iteration support to project items
        Schema::table('project_items', function (Blueprint $table) {
            $table->foreignUlid('iteration_id')->nullable()->constrained('project_iterations')->onDelete('set null');
            $table->json('labels')->nullable(); // GitHub-style labels
            $table->integer('estimate')->nullable(); // Story points or time estimate
            $table->decimal('progress', 5, 2)->default(0); // Progress percentage
            $table->index('iteration_id');
        });

        // Enhance project fields with GitHub v2 features
        Schema::table('project_fields', function (Blueprint $table) {
            // Add more field types
            $table->json('validation_rules')->nullable(); // Field validation rules
            $table->boolean('show_in_card')->default(true); // Show field in card view
            $table->string('icon')->nullable(); // Field icon
            $table->string('color')->nullable(); // Field color theme
        });

        // Add enhanced view options
        Schema::table('project_views', function (Blueprint $table) {
            $table->json('card_fields')->nullable(); // Fields to show in card layout
            $table->json('grouping_options')->nullable(); // Advanced grouping options
            $table->integer('items_per_page')->default(50);
            $table->boolean('show_item_count')->default(true);
            $table->string('empty_state_message')->nullable();
        });

        // Add saved filters
        Schema::create('project_saved_filters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->string('name');
            $table->json('filter_config');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'user_id']);
            $table->unique(['project_id', 'user_id', 'name']);
        });

        // Add project templates
        Schema::create('project_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('default_fields'); // Template fields configuration
            $table->json('default_views'); // Template views configuration
            $table->json('default_workflows')->nullable(); // Template workflows
            $table->boolean('is_public')->default(false);
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            $table->timestamps();

            $table->index('organization_id');
            $table->index('is_public');
        });

        // Add project insights/analytics
        Schema::create('project_insights', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->date('date');
            $table->json('metrics'); // Daily metrics snapshot
            $table->timestamps();

            $table->unique(['project_id', 'date']);
            $table->index(['project_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_insights');
        Schema::dropIfExists('project_templates');
        Schema::dropIfExists('project_saved_filters');
        
        Schema::table('project_views', function (Blueprint $table) {
            $table->dropColumn([
                'card_fields', 'grouping_options', 'items_per_page', 
                'show_item_count', 'empty_state_message'
            ]);
        });

        Schema::table('project_fields', function (Blueprint $table) {
            $table->dropColumn(['validation_rules', 'show_in_card', 'icon', 'color']);
        });

        Schema::table('project_items', function (Blueprint $table) {
            $table->dropForeign(['iteration_id']);
            $table->dropColumn(['iteration_id', 'labels', 'estimate', 'progress']);
        });

        Schema::dropIfExists('project_iterations');
    }
};