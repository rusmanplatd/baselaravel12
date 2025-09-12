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
        Schema::create('meeting_breakout_rooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->string('room_name');
            $table->string('room_sid')->nullable(); // LiveKit room SID
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->integer('room_number');
            $table->integer('max_participants')->default(10);
            $table->integer('current_participants')->default(0);
            
            // Room status
            $table->enum('status', ['created', 'active', 'closed', 'archived'])->default('created');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            
            // Room settings
            $table->json('room_settings')->nullable(); // Audio/video permissions, etc.
            $table->boolean('auto_assign')->default(false); // Automatically assign participants
            $table->boolean('allow_return_main')->default(true); // Allow return to main room
            $table->boolean('moderator_can_join')->default(true);
            
            // Assignment and management
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            $table->json('assigned_participants')->nullable(); // ULIDs of assigned attendees
            
            $table->timestamps();
            
            $table->index(['meeting_id', 'status']);
            $table->index(['room_number', 'meeting_id']);
            $table->unique(['meeting_id', 'room_number']);
        });

        // Participant assignments to breakout rooms
        Schema::create('meeting_breakout_room_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('breakout_room_id')->constrained('meeting_breakout_rooms')->onDelete('cascade');
            $table->foreignUlid('attendee_id')->constrained('meeting_attendees')->onDelete('cascade');
            $table->enum('assignment_type', ['manual', 'automatic', 'self_selected'])->default('manual');
            $table->enum('status', ['assigned', 'joined', 'left', 'moved'])->default('assigned');
            
            // Timing information
            $table->timestamp('assigned_at');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            
            // Assignment metadata
            $table->foreignUlid('assigned_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->text('assignment_reason')->nullable();
            
            $table->timestamps();
            
            $table->index(['breakout_room_id', 'status']);
            $table->index(['attendee_id', 'status']);
            $table->unique(['breakout_room_id', 'attendee_id']);
        });

        // Breakout room sessions for analytics
        Schema::create('meeting_breakout_room_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('breakout_room_id')->constrained('meeting_breakout_rooms')->onDelete('cascade');
            $table->integer('session_number')->default(1);
            
            // Session timing
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            
            // Participant statistics
            $table->integer('max_participants')->default(0);
            $table->integer('avg_participants')->default(0);
            $table->json('participant_timeline')->nullable(); // Join/leave events
            
            // Activity metrics
            $table->integer('total_messages')->default(0);
            $table->integer('screen_shares')->default(0);
            $table->boolean('recording_enabled')->default(false);
            $table->string('recording_url')->nullable();
            
            // Quality metrics
            $table->json('quality_metrics')->nullable(); // Audio/video quality stats
            $table->json('technical_issues')->nullable(); // Connection problems, etc.
            
            $table->timestamps();
            
            $table->index(['breakout_room_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_breakout_room_sessions');
        Schema::dropIfExists('meeting_breakout_room_participants');
        Schema::dropIfExists('meeting_breakout_rooms');
    }
};