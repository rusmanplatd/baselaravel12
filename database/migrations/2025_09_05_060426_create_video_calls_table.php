<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Video calls tracking table
        Schema::create('video_calls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('initiated_by')->constrained('sys_users')->onDelete('cascade');
            $table->string('livekit_room_name')->unique();
            $table->string('call_type')->default('video'); // video, audio
            $table->string('status')->default('initiated'); // initiated, ringing, active, ended, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->json('participants'); // Array of participant data
            $table->json('e2ee_settings')->nullable(); // Encryption settings
            $table->string('quality_settings')->nullable(); // JSON string of quality settings
            $table->json('metadata')->nullable(); // Additional call metadata
            $table->boolean('is_recorded')->default(false);
            $table->text('recording_url')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status']);
            $table->index(['initiated_by', 'created_at']);
            $table->index('livekit_room_name');
            $table->index('status');
        });

        // Video call participants table
        Schema::create('video_call_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('video_call_id')->constrained('video_calls')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->string('participant_identity')->unique(); // LiveKit participant identity
            $table->string('status')->default('invited'); // invited, joined, left, rejected, missed
            $table->timestamp('invited_at');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->json('connection_quality')->nullable(); // Connection quality metrics
            $table->json('media_tracks')->nullable(); // Published tracks info
            $table->string('device_info')->nullable(); // Device/browser info
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['video_call_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index('participant_identity');
        });

        // Video call events log table
        Schema::create('video_call_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('video_call_id')->constrained('video_calls')->onDelete('cascade');
            $table->foreignUlid('user_id')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->string('event_type'); // participant_joined, participant_left, track_published, etc.
            $table->json('event_data'); // Event-specific data
            $table->timestamp('event_timestamp');
            $table->timestamps();

            $table->index(['video_call_id', 'event_timestamp']);
            $table->index(['event_type', 'created_at']);
        });

        // Call quality metrics table
        Schema::create('video_call_quality_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('video_call_id')->constrained('video_calls')->onDelete('cascade');
            $table->foreignUlid('participant_id')->constrained('video_call_participants')->onDelete('cascade');
            $table->timestamp('measured_at');
            $table->json('video_metrics')->nullable(); // Resolution, bitrate, framerate, packet loss
            $table->json('audio_metrics')->nullable(); // Bitrate, packet loss, jitter, RTT
            $table->json('connection_metrics'); // Bandwidth, latency, connection type
            $table->integer('quality_score')->nullable(); // 1-5 quality rating
            $table->timestamps();

            $table->index(['video_call_id', 'measured_at']);
            $table->index(['participant_id', 'measured_at']);
        });

        // E2EE key exchange logs
        Schema::create('video_call_e2ee_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('video_call_id')->constrained('video_calls')->onDelete('cascade');
            $table->foreignUlid('participant_id')->constrained('video_call_participants')->onDelete('cascade');
            $table->string('key_operation'); // generate, distribute, rotate, revoke
            $table->string('encryption_algorithm'); // AES-GCM, ChaCha20-Poly1305
            $table->string('key_id'); // Unique identifier for the key
            $table->timestamp('operation_timestamp');
            $table->boolean('operation_success')->default(true);
            $table->text('error_details')->nullable();
            $table->json('operation_metadata')->nullable();
            $table->timestamps();

            $table->index(['video_call_id', 'operation_timestamp']);
            $table->index(['key_operation', 'operation_success']);
        });

        // Call recordings table
        Schema::create('video_call_recordings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('video_call_id')->constrained('video_calls')->onDelete('cascade');
            $table->string('recording_id'); // LiveKit recording ID
            $table->string('storage_type'); // s3, local, etc.
            $table->text('file_path');
            $table->string('file_format'); // mp4, webm
            $table->bigInteger('file_size')->nullable(); // bytes
            $table->integer('duration_seconds')->nullable();
            $table->json('recording_metadata')->nullable(); // Resolution, participants, etc.
            $table->boolean('is_encrypted')->default(false);
            $table->string('encryption_key_id')->nullable();
            $table->timestamp('recording_started_at');
            $table->timestamp('recording_ended_at')->nullable();
            $table->string('processing_status')->default('processing'); // processing, completed, failed
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->index(['video_call_id', 'processing_status']);
            $table->index('recording_id');
            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_call_recordings');
        Schema::dropIfExists('video_call_e2ee_logs');
        Schema::dropIfExists('video_call_quality_metrics');
        Schema::dropIfExists('video_call_events');
        Schema::dropIfExists('video_call_participants');
        Schema::dropIfExists('video_calls');
    }
};
