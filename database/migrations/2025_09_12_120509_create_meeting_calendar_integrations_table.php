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
        Schema::create('meeting_calendar_integrations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('calendar_event_id')->constrained('calendar_events')->onDelete('cascade');
            $table->foreignUlid('video_call_id')->nullable()->constrained('video_calls')->onDelete('set null');
            $table->string('meeting_type')->default('scheduled'); // scheduled, instant, recurring
            $table->string('meeting_provider')->default('livekit'); // livekit, zoom, teams, etc.
            $table->string('meeting_id')->nullable(); // External meeting ID if using 3rd party
            $table->text('join_url')->nullable(); // Direct join URL
            $table->text('host_url')->nullable(); // Host/admin URL
            $table->string('meeting_password')->nullable(); // Meeting password/PIN
            $table->json('meeting_settings')->nullable(); // Audio/video settings, recording, etc.
            $table->json('participant_limits')->nullable(); // Max participants, waiting room, etc.
            $table->boolean('auto_join_enabled')->default(false); // Auto-join from calendar
            $table->boolean('recording_enabled')->default(false);
            $table->boolean('e2ee_enabled')->default(true); // End-to-end encryption
            $table->string('status')->default('scheduled'); // scheduled, active, ended, cancelled
            $table->timestamp('meeting_started_at')->nullable();
            $table->timestamp('meeting_ended_at')->nullable();
            $table->json('participant_roster')->nullable(); // Expected participants
            $table->json('integration_metadata')->nullable(); // Provider-specific data
            $table->timestamps();

            // Indexes for performance
            $table->index(['calendar_event_id', 'status']);
            $table->index(['meeting_type', 'status']);
            $table->index('meeting_provider');
            $table->index('meeting_started_at');
        });

        // Meeting attendees table - links calendar attendees to actual meeting participants
        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_integration_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('user_id')->nullable()->constrained('sys_users')->onDelete('cascade');
            $table->string('email'); // For external attendees
            $table->string('name')->nullable();
            $table->string('role')->default('attendee'); // host, co-host, attendee, presenter
            $table->enum('invitation_status', ['pending', 'accepted', 'declined', 'tentative'])->default('pending');
            $table->enum('attendance_status', ['not_joined', 'joined', 'left', 'removed'])->default('not_joined');
            $table->timestamp('invited_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->json('attendee_metadata')->nullable(); // Device info, connection quality, etc.
            $table->timestamps();

            $table->unique(['meeting_integration_id', 'email']);
            $table->index(['user_id', 'attendance_status']);
            $table->index(['invitation_status', 'created_at']);
        });

        // Meeting reminders table
        Schema::create('meeting_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_integration_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->integer('minutes_before'); // Minutes before meeting start
            $table->string('reminder_type')->default('notification'); // notification, email, sms
            $table->string('status')->default('scheduled'); // scheduled, sent, failed
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('reminder_data')->nullable(); // Message content, delivery options
            $table->timestamps();

            $table->index(['scheduled_at', 'status']);
            $table->index(['user_id', 'reminder_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_reminders');
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meeting_calendar_integrations');
    }
};