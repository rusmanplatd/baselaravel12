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
        Schema::create('meeting_security_controls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('created_by')->constrained('sys_users')->onDelete('cascade');
            
            // Access Control Settings
            $table->boolean('require_authentication')->default(true);
            $table->boolean('require_meeting_password')->default(false);
            $table->string('meeting_password_hash')->nullable();
            $table->boolean('enable_waiting_room')->default(true);
            $table->boolean('require_host_approval')->default(true);
            
            // Participant Restrictions
            $table->json('allowed_domains')->nullable(); // Email domain restrictions
            $table->json('blocked_domains')->nullable(); // Blocked email domains
            $table->json('allowed_users')->nullable(); // Specific user ULIDs allowed
            $table->json('blocked_users')->nullable(); // Specific user ULIDs blocked
            $table->integer('max_participants')->nullable();
            $table->boolean('allow_anonymous_users')->default(false);
            
            // Meeting Content Security
            $table->boolean('disable_screen_sharing')->default(false);
            $table->boolean('disable_file_sharing')->default(false);
            $table->boolean('disable_chat')->default(false);
            $table->boolean('disable_private_chat')->default(false);
            $table->boolean('enable_end_to_end_encryption')->default(true);
            $table->boolean('require_encryption_verification')->default(false);
            
            // Recording Controls
            $table->enum('recording_permission', ['disabled', 'host_only', 'participants_allowed', 'participants_auto'])->default('host_only');
            $table->boolean('require_recording_consent')->default(true);
            $table->json('recording_restrictions')->nullable(); // Who can access recordings
            
            // Session Security
            $table->integer('session_timeout_minutes')->default(60); // Auto-kick inactive users
            $table->boolean('lock_meeting_after_start')->default(false);
            $table->integer('auto_lock_delay_minutes')->default(10);
            $table->boolean('enable_meeting_lobby')->default(true);
            
            // Monitoring and Alerts
            $table->boolean('monitor_suspicious_activity')->default(true);
            $table->boolean('log_all_participant_actions')->default(true);
            $table->boolean('alert_on_unauthorized_access')->default(true);
            $table->json('security_alert_contacts')->nullable(); // Who to notify of security issues
            
            // Compliance and Audit
            $table->boolean('enable_audit_trail')->default(true);
            $table->boolean('require_participation_consent')->default(false);
            $table->json('compliance_settings')->nullable(); // GDPR, HIPAA, etc.
            $table->string('data_retention_period')->default('90_days'); // Data retention policy
            
            $table->timestamps();
            
            $table->index(['meeting_id']);
            $table->index(['created_by']);
        });

        // Meeting Permission Roles - Define custom roles for meetings
        Schema::create('meeting_permission_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->string('role_name'); // host, moderator, presenter, participant, observer
            $table->text('description')->nullable();
            
            // Basic Permissions
            $table->boolean('can_join_meeting')->default(true);
            $table->boolean('can_speak')->default(true);
            $table->boolean('can_share_video')->default(true);
            $table->boolean('can_share_screen')->default(false);
            $table->boolean('can_chat')->default(true);
            $table->boolean('can_private_chat')->default(true);
            $table->boolean('can_share_files')->default(false);
            
            // Meeting Control Permissions
            $table->boolean('can_mute_participants')->default(false);
            $table->boolean('can_remove_participants')->default(false);
            $table->boolean('can_manage_waiting_room')->default(false);
            $table->boolean('can_control_recording')->default(false);
            $table->boolean('can_manage_breakout_rooms')->default(false);
            $table->boolean('can_end_meeting')->default(false);
            $table->boolean('can_lock_meeting')->default(false);
            
            // Advanced Permissions
            $table->boolean('can_change_layout')->default(false);
            $table->boolean('can_modify_settings')->default(false);
            $table->boolean('can_invite_participants')->default(false);
            $table->boolean('can_view_participant_list')->default(true);
            $table->boolean('can_access_recordings')->default(false);
            $table->boolean('can_create_polls')->default(false);
            
            // Time-based Restrictions
            $table->timestamp('access_valid_from')->nullable();
            $table->timestamp('access_valid_until')->nullable();
            $table->json('time_restrictions')->nullable(); // Day/hour restrictions
            
            $table->timestamps();
            
            $table->index(['meeting_id', 'role_name']);
            $table->unique(['meeting_id', 'role_name']);
        });

        // Meeting Participant Security - Individual participant security settings
        Schema::create('meeting_participant_security', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('attendee_id')->constrained('meeting_attendees')->onDelete('cascade');
            $table->foreignUlid('role_id')->nullable()->constrained('meeting_permission_roles')->onDelete('set null');
            
            // Access Control
            $table->enum('access_status', ['pending', 'approved', 'denied', 'suspended', 'removed'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->text('access_reason')->nullable(); // Reason for approval/denial
            
            // Security Verification
            $table->boolean('identity_verified')->default(false);
            $table->timestamp('identity_verified_at')->nullable();
            $table->string('verification_method')->nullable(); // email, sms, 2fa, etc.
            $table->json('verification_data')->nullable();
            
            // Session Security
            $table->string('session_token')->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('security_flags')->nullable(); // VPN detected, suspicious activity, etc.
            
            // Permission Overrides
            $table->json('permission_overrides')->nullable(); // Override role permissions for this user
            $table->json('restrictions')->nullable(); // Additional restrictions
            
            // Monitoring
            $table->integer('warning_count')->default(0);
            $table->timestamp('last_warning_at')->nullable();
            $table->text('warnings')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->text('flagged_reason')->nullable();
            
            $table->timestamps();
            
            $table->index(['meeting_id', 'access_status']);
            $table->index(['attendee_id', 'access_status']);
            $table->unique(['meeting_id', 'attendee_id']);
        });

        // Meeting Security Events - Audit trail for security-related events
        Schema::create('meeting_security_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('participant_id')->nullable()->constrained('meeting_participant_security')->onDelete('cascade');
            $table->foreignUlid('triggered_by')->nullable()->constrained('sys_users')->onDelete('set null');
            
            // Event Details
            $table->string('event_type'); // unauthorized_access, suspicious_activity, policy_violation, etc.
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('event_category'); // access_control, content_security, session_security, etc.
            $table->text('event_description');
            $table->json('event_data')->nullable(); // Additional context data
            
            // Response and Resolution
            $table->enum('status', ['detected', 'investigating', 'resolved', 'dismissed'])->default('detected');
            $table->text('response_action')->nullable(); // Action taken
            $table->foreignUlid('resolved_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            
            // Technical Details
            $table->string('source_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('geolocation')->nullable();
            $table->json('technical_details')->nullable();
            
            $table->timestamps();
            
            $table->index(['meeting_id', 'event_type']);
            $table->index(['severity', 'status']);
            $table->index(['created_at', 'event_type']);
        });

        // Meeting Access Tokens - Secure tokens for meeting access
        Schema::create('meeting_access_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('meeting_calendar_integrations')->onDelete('cascade');
            $table->foreignUlid('participant_id')->nullable()->constrained('meeting_participant_security')->onDelete('cascade');
            
            // Token Details
            $table->string('token_hash')->unique(); // Hashed token
            $table->enum('token_type', ['join_link', 'api_access', 'recording_access', 'admin_access'])->default('join_link');
            $table->timestamp('expires_at');
            $table->boolean('single_use')->default(false);
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            
            // Access Control
            $table->json('allowed_permissions')->nullable(); // Specific permissions for this token
            $table->json('ip_restrictions')->nullable(); // IP whitelist
            $table->json('time_restrictions')->nullable(); // Time-based access
            
            // Usage Tracking
            $table->timestamp('first_used_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('usage_log')->nullable(); // Track token usage
            
            // Security
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUlid('revoked_by')->nullable()->constrained('sys_users')->onDelete('set null');
            $table->text('revocation_reason')->nullable();
            
            $table->timestamps();
            
            $table->index(['meeting_id', 'token_type']);
            $table->index(['expires_at', 'is_revoked']);
            $table->index('token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_access_tokens');
        Schema::dropIfExists('meeting_security_events');
        Schema::dropIfExists('meeting_participant_security');
        Schema::dropIfExists('meeting_permission_roles');
        Schema::dropIfExists('meeting_security_controls');
    }
};