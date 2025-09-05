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
        // Rate limit tracking table
        Schema::create('rate_limits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key'); // Rate limit identifier (user_id, ip, etc.)
            $table->string('action'); // What action is being rate limited
            $table->integer('hits')->default(1);
            $table->integer('max_attempts');
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->timestamp('reset_at')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->text('metadata')->nullable(); // Additional context
            $table->timestamps();

            $table->unique(['key', 'action', 'window_start']);
            $table->index(['key', 'action']);
            $table->index(['window_end', 'is_blocked']);
            $table->index('reset_at');
        });

        // Abuse detection and tracking
        Schema::create('abuse_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('reported_user_id')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->foreignUlid('reporter_user_id')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->foreignUlid('conversation_id')->nullable()->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignUlid('message_id')->nullable()->constrained('chat_messages')->onDelete('cascade');
            $table->string('abuse_type'); // spam, harassment, inappropriate, malware, etc.
            $table->text('description')->nullable();
            $table->json('evidence')->nullable(); // Screenshots, logs, etc.
            $table->string('status')->default('pending'); // pending, reviewed, resolved, dismissed
            $table->text('resolution_notes')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->string('client_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['reported_user_id', 'status']);
            $table->index(['reporter_user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['abuse_type', 'status']);
        });

        // Suspicious activity tracking
        Schema::create('suspicious_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('sys_users')->onDelete('cascade');
            $table->string('activity_type'); // rapid_messaging, mass_file_upload, unusual_login, etc.
            $table->text('activity_description');
            $table->json('activity_data'); // Detailed activity information
            $table->integer('severity_score')->default(1); // 1-10 severity rating
            $table->string('detection_method'); // automated, manual, reported
            $table->string('status')->default('active'); // active, investigated, resolved, false_positive
            $table->text('investigation_notes')->nullable();
            $table->foreignUlid('investigated_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->timestamp('investigated_at')->nullable();
            $table->string('client_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'activity_type']);
            $table->index(['severity_score', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['detection_method', 'created_at']);
        });

        // User security penalties and restrictions
        Schema::create('user_penalties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->string('penalty_type'); // rate_limit, message_limit, file_limit, temporary_ban, etc.
            $table->string('reason');
            $table->text('description')->nullable();
            $table->json('restrictions'); // Specific restrictions applied
            $table->integer('severity_level')->default(1); // 1-5 escalation level
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('applied_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['penalty_type', 'is_active']);
            $table->index(['expires_at', 'is_active']);
            $table->index(['severity_level', 'created_at']);
        });

        // Message spam detection
        Schema::create('spam_detections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->string('detection_type'); // duplicate_content, excessive_links, keyword_match, ml_classifier
            $table->float('confidence_score', 3, 2); // 0.00 - 1.00 confidence
            $table->json('detection_details'); // What triggered the detection
            $table->string('action_taken'); // flagged, hidden, deleted, user_warned
            $table->boolean('is_false_positive')->default(false);
            $table->text('review_notes')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'detection_type']);
            $table->index(['conversation_id', 'created_at']);
            $table->index(['confidence_score', 'action_taken']);
            $table->index(['is_false_positive', 'reviewed_at']);
        });

        // IP-based restrictions and tracking
        Schema::create('ip_restrictions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('ip_address');
            $table->string('restriction_type'); // rate_limit, temporary_ban, permanent_ban, suspicious
            $table->string('reason');
            $table->text('description')->nullable();
            $table->integer('violation_count')->default(1);
            $table->json('restriction_settings'); // Rate limits, access restrictions
            $table->timestamp('first_violation_at');
            $table->timestamp('last_violation_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('applied_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->unique('ip_address');
            $table->index(['restriction_type', 'is_active']);
            $table->index(['expires_at', 'is_active']);
            $table->index('last_violation_at');
        });

        // Rate limiting configurations
        Schema::create('rate_limit_configs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('action_name'); // messages, file_uploads, api_calls, etc.
            $table->string('scope'); // global, per_user, per_ip, per_conversation
            $table->integer('max_attempts');
            $table->integer('window_seconds'); // Time window in seconds
            $table->integer('penalty_duration_seconds')->nullable(); // How long to block after limit hit
            $table->json('escalation_rules')->nullable(); // Escalating penalties
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['action_name', 'scope']);
            $table->index(['is_active', 'action_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_limit_configs');
        Schema::dropIfExists('ip_restrictions');
        Schema::dropIfExists('spam_detections');
        Schema::dropIfExists('user_penalties');
        Schema::dropIfExists('suspicious_activities');
        Schema::dropIfExists('abuse_reports');
        Schema::dropIfExists('rate_limits');
    }
};
