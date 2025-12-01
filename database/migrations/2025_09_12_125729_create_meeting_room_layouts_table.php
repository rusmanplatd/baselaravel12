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
        Schema::create('meeting_room_layouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            
            // Layout Identification
            $table->string('layout_name');
            $table->text('description')->nullable();
            $table->enum('layout_type', ['grid', 'speaker', 'presentation', 'custom', 'gallery', 'focus', 'sidebar', 'pip'])->default('grid');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Layout Configuration
            $table->json('layout_config'); // Main layout settings
            $table->json('responsive_breakpoints')->nullable(); // Different configs for screen sizes
            $table->json('participant_positioning')->nullable(); // Custom participant arrangements
            
            // Visual Customization
            $table->string('background_type')->default('none'); // none, color, image, video, blur
            $table->string('background_value')->nullable(); // Color hex, image URL, etc.
            $table->json('theme_settings')->nullable(); // Colors, fonts, styling
            $table->json('branding_elements')->nullable(); // Logos, watermarks, etc.
            
            // Layout Behavior
            $table->boolean('auto_arrange_participants')->default(true);
            $table->integer('max_visible_participants')->default(25);
            $table->boolean('highlight_active_speaker')->default(true);
            $table->boolean('show_participant_names')->default(true);
            $table->boolean('show_participant_status')->default(true);
            
            // Grid Layout Settings
            $table->integer('grid_columns')->nullable();
            $table->integer('grid_rows')->nullable();
            $table->string('grid_aspect_ratio')->default('16:9'); // 16:9, 4:3, 1:1, etc.
            $table->boolean('fill_grid_dynamically')->default(true);
            
            // Speaker Layout Settings
            $table->string('speaker_position')->default('center'); // center, left, right, top, bottom
            $table->string('speaker_size')->default('large'); // small, medium, large, full
            $table->boolean('show_speaker_thumbnails')->default(true);
            $table->integer('thumbnail_count')->default(6);
            
            // Presentation Layout Settings
            $table->string('content_position')->default('center'); // center, left, right
            $table->string('content_size')->default('large'); // small, medium, large, full
            $table->string('participants_position')->default('right'); // left, right, top, bottom
            $table->string('participants_size')->default('small');
            
            // Custom Layout Settings
            $table->json('custom_regions')->nullable(); // Define custom layout regions
            $table->json('region_rules')->nullable(); // Rules for populating regions
            $table->json('animation_settings')->nullable(); // Transition animations
            
            // Interactive Elements
            $table->boolean('enable_layout_switching')->default(true);
            $table->boolean('allow_participant_pinning')->default(true);
            $table->boolean('enable_spotlight_mode')->default(true);
            $table->boolean('show_layout_controls')->default(true);
            
            // Accessibility
            $table->boolean('high_contrast_mode')->default(false);
            $table->boolean('reduce_animations')->default(false);
            $table->json('accessibility_settings')->nullable();
            
            // Performance
            $table->string('video_quality')->default('auto'); // auto, low, medium, high
            $table->boolean('adaptive_quality')->default(true);
            $table->integer('frame_rate')->default(30);
            
            $table->timestamps();
            
            $table->index(['meeting_id', 'is_active']);
            $table->index(['layout_type', 'is_active']);
            $table->index(['created_by']);
        });

        // Meeting Layout Presets - Predefined layout templates
        Schema::create('meeting_layout_presets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            
            // Preset Information
            $table->string('preset_name');
            $table->text('description')->nullable();
            $table->string('category')->default('general'); // general, education, business, webinar, etc.
            $table->string('thumbnail_url')->nullable(); // Preview image
            $table->boolean('is_public')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->integer('usage_count')->default(0);
            
            // Layout Configuration
            $table->enum('layout_type', ['grid', 'speaker', 'presentation', 'custom', 'gallery', 'focus', 'sidebar', 'pip'])->default('grid');
            $table->json('preset_config'); // Complete layout configuration
            $table->json('customization_options')->nullable(); // What users can customize
            
            // Branding and Themes
            $table->json('brand_settings')->nullable(); // Default branding
            $table->json('color_schemes')->nullable(); // Available color options
            $table->json('font_options')->nullable(); // Font choices
            
            // Compatibility
            $table->json('supported_features')->nullable(); // What features this preset supports
            $table->json('device_compatibility')->nullable(); // Mobile, desktop, etc.
            $table->integer('min_participants')->default(1);
            $table->integer('max_participants')->default(100);
            
            $table->timestamps();
            
            $table->index(['organization_id', 'is_public']);
            $table->index(['category', 'is_featured']);
            $table->index('is_public');
        });

        // Meeting Layout Elements - Individual customizable elements
        Schema::create('meeting_layout_elements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('layout_id')->constrained('meeting_room_layouts')->onDelete('cascade');
            
            // Element Information
            $table->string('element_type'); // video_feed, chat_panel, toolbar, overlay, etc.
            $table->string('element_name');
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_draggable')->default(false);
            $table->boolean('is_resizable')->default(false);
            
            // Positioning
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->integer('width')->default(100);
            $table->integer('height')->default(100);
            $table->integer('z_index')->default(1);
            $table->string('position_unit')->default('percent'); // percent, pixels
            
            // Styling
            $table->json('styling_properties')->nullable(); // CSS-like properties
            $table->string('background_color')->nullable();
            $table->string('border_color')->nullable();
            $table->integer('border_width')->default(0);
            $table->integer('border_radius')->default(0);
            $table->float('opacity')->default(1.0);
            
            // Behavior
            $table->json('behavior_settings')->nullable(); // Click handlers, hover effects, etc.
            $table->json('animation_config')->nullable(); // Entry/exit animations
            $table->boolean('responsive_sizing')->default(true);
            
            // Content
            $table->json('content_config')->nullable(); // Element-specific content settings
            $table->json('data_bindings')->nullable(); // What data this element shows
            
            $table->timestamps();
            
            $table->index(['layout_id', 'element_type']);
            $table->index(['layout_id', 'z_index']);
        });

        // Meeting Branding Assets - Custom assets for meeting branding
        Schema::create('meeting_branding_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->nullable()->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
            $table->foreignUlid('uploaded_by')->constrained('sys_users')->onDelete('cascade');
            
            // Asset Information
            $table->string('asset_type'); // logo, background, overlay, watermark, etc.
            $table->string('asset_name');
            $table->text('description')->nullable();
            $table->string('file_path'); // MinIO storage path
            $table->string('file_url')->nullable(); // CDN/public URL
            $table->string('file_type'); // image/png, image/jpeg, video/mp4, etc.
            $table->bigInteger('file_size'); // bytes
            
            // Image/Video Properties
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('aspect_ratio')->nullable();
            $table->integer('duration_seconds')->nullable(); // For video assets
            
            // Usage Settings
            $table->json('usage_rules')->nullable(); // Where and how this asset can be used
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('usage_count')->default(0);
            
            // Processing Status
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('processing_results')->nullable(); // Thumbnails, optimizations, etc.
            $table->text('processing_error')->nullable();
            
            $table->timestamps();
            
            $table->index(['meeting_id', 'asset_type']);
            $table->index(['organization_id', 'asset_type']);
            $table->index(['asset_type', 'is_active']);
        });

        // Meeting Layout History - Track layout changes during meetings
        Schema::create('meeting_layout_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('layout_id')->constrained('meeting_room_layouts')->onDelete('cascade');
            $table->foreignUlid('changed_by')->nullable()->constrained('sys_users')->onDelete('set null');
            
            // Change Information
            $table->string('change_type'); // applied, modified, switched, etc.
            $table->json('previous_config')->nullable(); // Previous layout state
            $table->json('new_config'); // New layout state
            $table->text('change_reason')->nullable(); // Why the change was made
            
            // Context
            $table->integer('participant_count')->nullable(); // Number of participants when changed
            $table->integer('duration_active_seconds')->nullable(); // How long this layout was active
            $table->boolean('was_automatic')->default(false); // Auto-change vs manual
            
            // Performance Impact
            $table->json('performance_metrics')->nullable(); // CPU, bandwidth impact
            $table->json('participant_feedback')->nullable(); // User reactions to change
            
            $table->timestamps();
            
            $table->index(['meeting_id', 'created_at']);
            $table->index(['layout_id', 'created_at']);
            $table->index('changed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_layout_history');
        Schema::dropIfExists('meeting_branding_assets');
        Schema::dropIfExists('meeting_layout_elements');
        Schema::dropIfExists('meeting_layout_presets');
        Schema::dropIfExists('meeting_room_layouts');
    }
};