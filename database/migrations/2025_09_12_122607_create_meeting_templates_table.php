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
        Schema::create('meeting_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
            
            // Template settings
            $table->json('meeting_settings'); // Audio/video defaults, recording, etc.
            $table->json('participant_limits'); // Max participants, waiting room, etc.
            $table->boolean('e2ee_enabled')->default(true);
            $table->boolean('recording_enabled')->default(false);
            $table->boolean('auto_join_enabled')->default(false);
            
            // Layout and appearance
            $table->string('default_layout')->default('grid'); // grid, speaker, focus
            $table->json('layout_settings')->nullable();
            $table->string('background_image')->nullable();
            $table->json('branding_settings')->nullable();
            
            // Automation settings
            $table->boolean('auto_start_recording')->default(false);
            $table->boolean('auto_send_reminders')->default(true);
            $table->boolean('auto_generate_transcripts')->default(false);
            $table->json('automation_rules')->nullable();
            
            // Default attendees and permissions
            $table->json('default_attendees')->nullable(); // Pre-configured attendee list
            $table->json('permission_presets')->nullable(); // Role-based permission templates
            
            // Breakout room settings
            $table->boolean('enable_breakout_rooms')->default(false);
            $table->integer('default_breakout_count')->nullable();
            $table->json('breakout_settings')->nullable();
            
            // Integration settings
            $table->json('integration_settings')->nullable(); // Third-party integrations
            $table->json('webhook_settings')->nullable();
            
            // Usage and analytics
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false); // Can be used by other organization members
            
            $table->timestamps();
            
            $table->index(['organization_id', 'is_active']);
            $table->index(['created_by', 'is_active']);
            $table->index('is_public');
            $table->index('last_used_at');
        });

        // Meeting template categories for organization
        Schema::create('meeting_template_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color')->default('#3B82F6');
            $table->string('icon')->nullable();
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['organization_id', 'is_active']);
        });

        // Link templates to categories
        Schema::create('meeting_template_category_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('template_id')->constrained('meeting_templates')->onDelete('cascade');
            $table->foreignUlid('category_id')->constrained('meeting_template_categories')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['template_id', 'category_id']);
        });

        // Template usage analytics
        Schema::create('meeting_template_usage_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('template_id')->constrained('meeting_templates')->onDelete('cascade');
            $table->foreignUlid('meeting_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('used_by')->constrained('sys_users')->onDelete('cascade');
            $table->json('applied_settings')->nullable(); // Settings that were applied from template
            $table->json('modified_settings')->nullable(); // Settings that were changed from template defaults
            $table->timestamps();
            
            $table->index(['template_id', 'created_at']);
            $table->index(['used_by', 'created_at']);
        });

        // Scheduled recurring meetings based on templates
        Schema::create('scheduled_meeting_series', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('template_id')->constrained('meeting_templates')->onDelete('cascade');
            $table->foreignUlid('calendar_id')->constrained('calendars')->onDelete('cascade');
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            
            // Series information
            $table->string('series_name');
            $table->text('series_description')->nullable();
            $table->string('recurrence_pattern'); // daily, weekly, monthly, custom
            $table->json('recurrence_settings'); // Detailed recurrence configuration
            
            // Time settings
            $table->dateTime('series_start_date');
            $table->dateTime('series_end_date')->nullable();
            $table->time('default_start_time');
            $table->integer('default_duration_minutes');
            $table->string('timezone')->default('UTC');
            
            // Generation settings
            $table->integer('max_occurrences')->nullable(); // Limit number of meetings
            $table->integer('advance_generation_days')->default(30); // How far ahead to generate
            $table->integer('generated_count')->default(0);
            $table->dateTime('last_generation_date')->nullable();
            $table->dateTime('next_generation_due')->nullable();
            
            // Status
            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])->default('active');
            $table->json('series_metadata')->nullable();
            
            $table->timestamps();
            
            $table->index(['status', 'next_generation_due']);
            $table->index(['created_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_meeting_series');
        Schema::dropIfExists('meeting_template_usage_logs');
        Schema::dropIfExists('meeting_template_category_assignments');
        Schema::dropIfExists('meeting_template_categories');
        Schema::dropIfExists('meeting_templates');
    }
};