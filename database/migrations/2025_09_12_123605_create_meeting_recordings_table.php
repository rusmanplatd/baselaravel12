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
        Schema::create('meeting_recordings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('breakout_room_id')->nullable()->constrained('meeting_breakout_rooms')->onDelete('cascade');
            $table->foreignUlid('initiated_by')->constrained('sys_users')->onDelete('cascade');
            
            // Recording identification
            $table->string('recording_id')->unique(); // LiveKit recording ID
            $table->string('recording_name');
            $table->text('description')->nullable();
            $table->enum('recording_type', ['full_meeting', 'breakout_room', 'screen_share', 'audio_only'])->default('full_meeting');
            
            // Recording status and timing
            $table->enum('status', ['starting', 'recording', 'processing', 'completed', 'failed', 'stopped'])->default('starting');
            $table->timestamp('started_at');
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            
            // File information
            $table->string('file_path')->nullable(); // MinIO/S3 path
            $table->string('file_url')->nullable(); // Signed URL for access
            $table->bigInteger('file_size')->nullable(); // Bytes
            $table->string('file_format')->default('mp4'); // mp4, webm, etc.
            $table->json('video_metadata')->nullable(); // Resolution, codec, etc.
            
            // Quality and settings
            $table->string('video_resolution')->default('1920x1080');
            $table->integer('video_bitrate')->default(4000000); // 4 Mbps
            $table->integer('audio_bitrate')->default(128000); // 128 kbps
            $table->string('video_codec')->default('h264');
            $table->string('audio_codec')->default('aac');
            
            // Layout and composition
            $table->enum('layout_type', ['grid', 'speaker', 'presentation', 'custom'])->default('grid');
            $table->json('layout_settings')->nullable();
            $table->json('participant_layout')->nullable(); // Which participants were visible
            
            // Access control and sharing
            $table->boolean('is_public')->default(false);
            $table->json('access_permissions')->nullable(); // Who can view/download
            $table->string('share_token')->nullable(); // Token for sharing
            $table->timestamp('share_token_expires_at')->nullable();
            
            // Processing information
            $table->json('processing_details')->nullable(); // Post-processing info
            $table->text('processing_error')->nullable(); // Error details if failed
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            
            // Analytics and usage
            $table->integer('view_count')->default(0);
            $table->integer('download_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->json('analytics_data')->nullable();
            
            // Retention and lifecycle
            $table->timestamp('auto_delete_at')->nullable();
            $table->enum('retention_policy', ['keep_forever', 'delete_after_30_days', 'delete_after_90_days', 'delete_after_1_year', 'custom'])->default('delete_after_90_days');
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['meeting_id', 'status']);
            $table->index(['initiated_by', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('recording_type');
            $table->index('auto_delete_at');
            $table->index(['is_public', 'status']);
        });

        // Recording participants - tracks who was in the recording
        Schema::create('meeting_recording_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('recording_id')->constrained('meeting_recordings')->onDelete('cascade');
            $table->foreignUlid('attendee_id')->constrained('meeting_attendees')->onDelete('cascade');
            
            // Participation details
            $table->timestamp('joined_recording_at');
            $table->timestamp('left_recording_at')->nullable();
            $table->integer('participation_duration_seconds')->nullable();
            $table->boolean('was_speaking')->default(false);
            $table->boolean('had_video')->default(false);
            $table->boolean('was_screen_sharing')->default(false);
            
            // Track position in recording
            $table->json('timeline_events')->nullable(); // Join/leave events with timestamps
            $table->json('participation_metadata')->nullable(); // Additional participant info
            
            $table->timestamps();
            
            $table->index(['recording_id', 'joined_recording_at']);
            $table->index('attendee_id');
        });

        // Recording segments - for breaking large recordings into segments
        Schema::create('meeting_recording_segments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('recording_id')->constrained('meeting_recordings')->onDelete('cascade');
            
            // Segment information
            $table->string('segment_name');
            $table->integer('segment_number');
            $table->timestamp('segment_start_time');
            $table->timestamp('segment_end_time');
            $table->integer('segment_duration_seconds');
            
            // File details
            $table->string('segment_file_path')->nullable();
            $table->string('segment_file_url')->nullable();
            $table->bigInteger('segment_file_size')->nullable();
            
            // Processing status
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->text('processing_error')->nullable();
            
            // Content metadata
            $table->json('content_analysis')->nullable(); // AI analysis of segment content
            $table->json('speaker_timeline')->nullable(); // Who spoke when
            $table->json('activity_summary')->nullable(); // Screen shares, etc.
            
            $table->timestamps();
            
            $table->index(['recording_id', 'segment_number']);
            $table->unique(['recording_id', 'segment_number']);
        });

        // Recording access logs - audit trail for who accessed recordings
        Schema::create('meeting_recording_access_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('recording_id')->constrained('meeting_recordings')->onDelete('cascade');
            $table->foreignUlid('user_id')->nullable()->constrained('sys_users')->onDelete('set null');
            
            // Access details
            $table->enum('access_type', ['view', 'download', 'share', 'delete', 'modify'])->default('view');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('accessed_at');
            
            // Access context
            $table->string('access_method')->nullable(); // web, api, mobile app
            $table->json('access_metadata')->nullable(); // Additional context
            $table->integer('bytes_transferred')->nullable(); // For downloads
            $table->boolean('access_granted')->default(true);
            $table->text('access_denied_reason')->nullable();
            
            $table->timestamps();
            
            $table->index(['recording_id', 'accessed_at']);
            $table->index(['user_id', 'access_type']);
            $table->index('accessed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_recording_access_logs');
        Schema::dropIfExists('meeting_recording_segments');
        Schema::dropIfExists('meeting_recording_participants');
        Schema::dropIfExists('meeting_recordings');
    }
};