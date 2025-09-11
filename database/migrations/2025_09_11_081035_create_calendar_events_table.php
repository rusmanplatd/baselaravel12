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
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('calendar_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('color', 7)->nullable(); // Override calendar color
            $table->enum('status', ['confirmed', 'tentative', 'cancelled'])->default('confirmed');
            $table->enum('visibility', ['public', 'private', 'confidential'])->default('public');
            $table->string('recurrence_rule')->nullable(); // RRULE for recurring events
            $table->ulid('recurrence_parent_id')->nullable(); // For recurring event instances
            $table->json('attendees')->nullable(); // Array of attendee emails/user ids
            $table->json('reminders')->nullable(); // Array of reminder settings
            $table->json('metadata')->nullable(); // Additional event data
            $table->string('meeting_url')->nullable(); // Video meeting link
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('calendar_id')->references('id')->on('calendars')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('sys_users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('sys_users')->onDelete('set null');
            
            $table->index(['calendar_id', 'starts_at']);
            $table->index(['starts_at', 'ends_at']);
            $table->index(['status', 'visibility']);
            $table->index('recurrence_parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
