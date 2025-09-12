<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MeetingSecurityControl extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'meeting_id',
        'created_by',
        'require_authentication',
        'require_meeting_password',
        'meeting_password_hash',
        'enable_waiting_room',
        'require_host_approval',
        'allowed_domains',
        'blocked_domains',
        'allowed_users',
        'blocked_users',
        'max_participants',
        'allow_anonymous_users',
        'disable_screen_sharing',
        'disable_file_sharing',
        'disable_chat',
        'disable_private_chat',
        'enable_end_to_end_encryption',
        'require_encryption_verification',
        'recording_permission',
        'require_recording_consent',
        'recording_restrictions',
        'session_timeout_minutes',
        'lock_meeting_after_start',
        'auto_lock_delay_minutes',
        'enable_meeting_lobby',
        'monitor_suspicious_activity',
        'log_all_participant_actions',
        'alert_on_unauthorized_access',
        'security_alert_contacts',
        'enable_audit_trail',
        'require_participation_consent',
        'compliance_settings',
        'data_retention_period',
    ];

    protected $casts = [
        'require_authentication' => 'boolean',
        'require_meeting_password' => 'boolean',
        'enable_waiting_room' => 'boolean',
        'require_host_approval' => 'boolean',
        'allowed_domains' => 'array',
        'blocked_domains' => 'array',
        'allowed_users' => 'array',
        'blocked_users' => 'array',
        'max_participants' => 'integer',
        'allow_anonymous_users' => 'boolean',
        'disable_screen_sharing' => 'boolean',
        'disable_file_sharing' => 'boolean',
        'disable_chat' => 'boolean',
        'disable_private_chat' => 'boolean',
        'enable_end_to_end_encryption' => 'boolean',
        'require_encryption_verification' => 'boolean',
        'require_recording_consent' => 'boolean',
        'recording_restrictions' => 'array',
        'session_timeout_minutes' => 'integer',
        'lock_meeting_after_start' => 'boolean',
        'auto_lock_delay_minutes' => 'integer',
        'enable_meeting_lobby' => 'boolean',
        'monitor_suspicious_activity' => 'boolean',
        'log_all_participant_actions' => 'boolean',
        'alert_on_unauthorized_access' => 'boolean',
        'security_alert_contacts' => 'array',
        'enable_audit_trail' => 'boolean',
        'require_participation_consent' => 'boolean',
        'compliance_settings' => 'array',
    ];

    // Hide sensitive data
    protected $hidden = [
        'meeting_password_hash',
    ];

    // Relationships
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MeetingCalendarIntegration::class, 'meeting_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function permissionRoles(): HasMany
    {
        return $this->hasMany(MeetingPermissionRole::class, 'meeting_id', 'meeting_id');
    }

    public function participantSecurity(): HasMany
    {
        return $this->hasMany(MeetingParticipantSecurity::class, 'meeting_id', 'meeting_id');
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(MeetingSecurityEvent::class, 'meeting_id', 'meeting_id');
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(MeetingAccessToken::class, 'meeting_id', 'meeting_id');
    }

    // Password Management
    public function setMeetingPassword(string $password): void
    {
        $this->update([
            'require_meeting_password' => true,
            'meeting_password_hash' => Hash::make($password),
        ]);
    }

    public function verifyMeetingPassword(string $password): bool
    {
        if (!$this->require_meeting_password || !$this->meeting_password_hash) {
            return true; // No password required
        }

        return Hash::check($password, $this->meeting_password_hash);
    }

    public function removeMeetingPassword(): void
    {
        $this->update([
            'require_meeting_password' => false,
            'meeting_password_hash' => null,
        ]);
    }

    // Access Control Methods
    public function canUserJoin(User $user, string $email = null): bool
    {
        // Check if user is blocked
        if ($this->isUserBlocked($user)) {
            return false;
        }

        // Check if authentication is required and user is authenticated
        if ($this->require_authentication && !$user->id) {
            return false;
        }

        // Check domain restrictions
        $userEmail = $email ?? $user->email;
        if (!$this->isEmailDomainAllowed($userEmail)) {
            return false;
        }

        // Check user-specific allowlist
        if (!empty($this->allowed_users) && !in_array($user->id, $this->allowed_users)) {
            return false;
        }

        // Check participant limit
        if ($this->max_participants && $this->meeting->getActiveAttendeeCount() >= $this->max_participants) {
            return false;
        }

        return true;
    }

    public function isUserBlocked(User $user): bool
    {
        return !empty($this->blocked_users) && in_array($user->id, $this->blocked_users);
    }

    public function isEmailDomainAllowed(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1));

        // Check blocked domains first
        if (!empty($this->blocked_domains) && in_array($domain, $this->blocked_domains)) {
            return false;
        }

        // If allowed domains are specified, check if domain is in the list
        if (!empty($this->allowed_domains)) {
            return in_array($domain, $this->allowed_domains);
        }

        // No domain restrictions
        return true;
    }

    public function blockUser(string $userId, string $reason = null): void
    {
        $blockedUsers = $this->blocked_users ?? [];
        if (!in_array($userId, $blockedUsers)) {
            $blockedUsers[] = $userId;
            $this->update(['blocked_users' => $blockedUsers]);

            // Log security event
            $this->logSecurityEvent('user_blocked', [
                'blocked_user_id' => $userId,
                'reason' => $reason,
            ], 'medium');
        }
    }

    public function unblockUser(string $userId): void
    {
        $blockedUsers = $this->blocked_users ?? [];
        $blockedUsers = array_filter($blockedUsers, fn($id) => $id !== $userId);
        $this->update(['blocked_users' => array_values($blockedUsers)]);
    }

    public function allowUser(string $userId): void
    {
        $allowedUsers = $this->allowed_users ?? [];
        if (!in_array($userId, $allowedUsers)) {
            $allowedUsers[] = $userId;
            $this->update(['allowed_users' => $allowedUsers]);
        }
    }

    public function removeAllowedUser(string $userId): void
    {
        $allowedUsers = $this->allowed_users ?? [];
        $allowedUsers = array_filter($allowedUsers, fn($id) => $id !== $userId);
        $this->update(['allowed_users' => array_values($allowedUsers)]);
    }

    // Security Event Logging
    public function logSecurityEvent(
        string $eventType,
        array $eventData = [],
        string $severity = 'medium',
        string $category = 'general',
        User $triggeredBy = null
    ): MeetingSecurityEvent {
        return $this->securityEvents()->create([
            'triggered_by' => $triggeredBy?->id ?? auth()->id(),
            'event_type' => $eventType,
            'severity' => $severity,
            'event_category' => $category,
            'event_description' => $this->generateEventDescription($eventType, $eventData),
            'event_data' => $eventData,
            'source_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'technical_details' => [
                'timestamp' => now()->toISOString(),
                'session_id' => session()->getId(),
            ],
        ]);
    }

    // Token Management
    public function generateAccessToken(
        string $type = 'join_link',
        ?User $participant = null,
        array $permissions = [],
        int $expirationHours = 24
    ): string {
        $token = Str::random(64);
        
        $this->accessTokens()->create([
            'participant_id' => $participant?->id,
            'token_hash' => hash('sha256', $token),
            'token_type' => $type,
            'expires_at' => now()->addHours($expirationHours),
            'allowed_permissions' => $permissions,
            'ip_restrictions' => request()->ip() ? [request()->ip()] : null,
        ]);

        return $token;
    }

    public function verifyAccessToken(string $token): ?MeetingAccessToken
    {
        $tokenHash = hash('sha256', $token);
        
        return $this->accessTokens()
            ->where('token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->where('is_revoked', false)
            ->first();
    }

    public function revokeAccessToken(string $token, string $reason = null): bool
    {
        $tokenHash = hash('sha256', $token);
        
        return $this->accessTokens()
            ->where('token_hash', $tokenHash)
            ->update([
                'is_revoked' => true,
                'revoked_at' => now(),
                'revoked_by' => auth()->id(),
                'revocation_reason' => $reason,
            ]) > 0;
    }

    // Security Monitoring
    public function detectSuspiciousActivity(array $context = []): bool
    {
        if (!$this->monitor_suspicious_activity) {
            return false;
        }

        $suspiciousFlags = [];

        // Check for rapid login attempts
        $recentAttempts = $this->securityEvents()
            ->where('event_type', 'login_attempt')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($recentAttempts >= 10) {
            $suspiciousFlags[] = 'rapid_login_attempts';
        }

        // Check for unusual IP patterns
        $recentIPs = $this->securityEvents()
            ->where('created_at', '>=', now()->subHour())
            ->distinct('source_ip')
            ->count();

        if ($recentIPs >= 5) {
            $suspiciousFlags[] = 'multiple_ip_addresses';
        }

        // Check for failed access attempts
        $failedAttempts = $this->securityEvents()
            ->where('event_type', 'unauthorized_access')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        if ($failedAttempts >= 5) {
            $suspiciousFlags[] = 'multiple_failed_attempts';
        }

        if (!empty($suspiciousFlags)) {
            $this->logSecurityEvent('suspicious_activity_detected', [
                'flags' => $suspiciousFlags,
                'context' => $context,
            ], 'high', 'security_monitoring');

            return true;
        }

        return false;
    }

    // Security Policy Enforcement
    public function enforceSecurityPolicy(User $user, string $action, array $context = []): bool
    {
        // Check session timeout
        if ($this->session_timeout_minutes > 0) {
            $lastActivity = $context['last_activity'] ?? now();
            if (now()->diffInMinutes($lastActivity) > $this->session_timeout_minutes) {
                $this->logSecurityEvent('session_timeout', [
                    'user_id' => $user->id,
                    'timeout_minutes' => $this->session_timeout_minutes,
                ], 'low', 'session_security');
                
                return false;
            }
        }

        // Check if meeting is locked
        if ($this->meeting->status === 'locked' && !$this->canBypassMeetingLock($user)) {
            $this->logSecurityEvent('access_denied_meeting_locked', [
                'user_id' => $user->id,
                'action' => $action,
            ], 'medium', 'access_control');
            
            return false;
        }

        return true;
    }

    protected function canBypassMeetingLock(User $user): bool
    {
        // Meeting host can always bypass lock
        if ($this->meeting->attendees()->where('user_id', $user->id)->where('role', 'host')->exists()) {
            return true;
        }

        // Check for special permissions
        $participantSecurity = $this->participantSecurity()
            ->whereHas('attendee', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if ($participantSecurity && $participantSecurity->hasPermission('can_bypass_lock')) {
            return true;
        }

        return false;
    }

    // Compliance and Audit
    public function getComplianceReport(): array
    {
        return [
            'meeting_id' => $this->meeting_id,
            'security_settings' => [
                'authentication_required' => $this->require_authentication,
                'password_protected' => $this->require_meeting_password,
                'waiting_room_enabled' => $this->enable_waiting_room,
                'e2e_encryption' => $this->enable_end_to_end_encryption,
                'audit_trail' => $this->enable_audit_trail,
            ],
            'participant_controls' => [
                'max_participants' => $this->max_participants,
                'domain_restrictions' => !empty($this->allowed_domains),
                'user_restrictions' => !empty($this->allowed_users) || !empty($this->blocked_users),
            ],
            'content_security' => [
                'screen_sharing_disabled' => $this->disable_screen_sharing,
                'file_sharing_disabled' => $this->disable_file_sharing,
                'chat_disabled' => $this->disable_chat,
                'private_chat_disabled' => $this->disable_private_chat,
            ],
            'recording_controls' => [
                'recording_permission' => $this->recording_permission,
                'consent_required' => $this->require_recording_consent,
                'access_restricted' => !empty($this->recording_restrictions),
            ],
            'monitoring' => [
                'activity_monitoring' => $this->monitor_suspicious_activity,
                'action_logging' => $this->log_all_participant_actions,
                'security_alerts' => $this->alert_on_unauthorized_access,
            ],
            'compliance' => [
                'participation_consent' => $this->require_participation_consent,
                'data_retention_period' => $this->data_retention_period,
                'compliance_frameworks' => $this->compliance_settings,
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    protected function generateEventDescription(string $eventType, array $eventData): string
    {
        return match ($eventType) {
            'unauthorized_access' => 'Unauthorized access attempt detected',
            'suspicious_activity' => 'Suspicious activity patterns detected',
            'user_blocked' => 'User blocked from meeting access',
            'password_failure' => 'Meeting password verification failed',
            'session_timeout' => 'User session timed out due to inactivity',
            'access_denied_meeting_locked' => 'Access denied - meeting is locked',
            'permission_violation' => 'User attempted action without sufficient permissions',
            'encryption_failure' => 'End-to-end encryption verification failed',
            'compliance_violation' => 'Compliance policy violation detected',
            default => "Security event: {$eventType}",
        };
    }
}