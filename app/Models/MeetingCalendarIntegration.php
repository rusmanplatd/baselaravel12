<?php

namespace App\Models;

use App\Models\Chat\VideoCall;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MeetingCalendarIntegration extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'calendar_event_id',
        'video_call_id',
        'meeting_type',
        'meeting_provider',
        'meeting_id',
        'room_name',
        'join_url',
        'host_url',
        'meeting_password',
        'meeting_settings',
        'participant_limits',
        'auto_join_enabled',
        'recording_enabled',
        'e2ee_enabled',
        'status',
        'meeting_started_at',
        'meeting_ended_at',
        'participant_roster',
        'integration_metadata',
    ];

    protected $casts = [
        'meeting_settings' => 'array',
        'participant_limits' => 'array',
        'participant_roster' => 'array',
        'integration_metadata' => 'array',
        'auto_join_enabled' => 'boolean',
        'recording_enabled' => 'boolean',
        'e2ee_enabled' => 'boolean',
        'meeting_started_at' => 'datetime',
        'meeting_ended_at' => 'datetime',
    ];

    // Relationships
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function videoCall(): BelongsTo
    {
        return $this->belongsTo(VideoCall::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class, 'meeting_integration_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(MeetingReminder::class, 'meeting_integration_id');
    }

    public function breakoutRooms(): HasMany
    {
        return $this->hasMany(MeetingBreakoutRoom::class, 'meeting_id');
    }

    public function activeBreakoutRooms(): HasMany
    {
        return $this->breakoutRooms()->where('status', 'active');
    }

    public function securityControl(): HasOne
    {
        return $this->hasOne(MeetingSecurityControl::class, 'meeting_id');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(MeetingRecording::class, 'meeting_id');
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(MeetingRoomLayout::class, 'meeting_id');
    }

    public function activeLayout(): HasMany
    {
        return $this->layouts()->where('is_default', true);
    }

    // Scopes
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEnded($query)
    {
        return $query->where('status', 'ended');
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('meeting_provider', $provider);
    }

    public function scopeWithE2EE($query)
    {
        return $query->where('e2ee_enabled', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereHas('calendarEvent', function ($q) {
            $q->where('starts_at', '>', now());
        });
    }

    // Helper methods
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRecurring(): bool
    {
        return $this->meeting_type === 'recurring';
    }

    public function hasRecordingEnabled(): bool
    {
        return $this->recording_enabled;
    }

    public function hasE2EEEnabled(): bool
    {
        return $this->e2ee_enabled;
    }

    public function canAutoJoin(): bool
    {
        return $this->auto_join_enabled;
    }

    public function getMeetingDuration(): ?int
    {
        if (!$this->meeting_started_at || !$this->meeting_ended_at) {
            return null;
        }

        return $this->meeting_started_at->diffInMinutes($this->meeting_ended_at);
    }

    public function getAttendeeCount(): int
    {
        return $this->attendees()->count();
    }

    public function getActiveAttendeeCount(): int
    {
        return $this->attendees()->where('attendance_status', 'joined')->count();
    }

    public function getAcceptedAttendeeCount(): int
    {
        return $this->attendees()->where('invitation_status', 'accepted')->count();
    }

    public function startMeeting(): void
    {
        $this->update([
            'status' => 'active',
            'meeting_started_at' => now(),
        ]);
    }

    public function endMeeting(): void
    {
        $this->update([
            'status' => 'ended',
            'meeting_ended_at' => now(),
        ]);

        // Update attendees who are still joined
        $this->attendees()
            ->where('attendance_status', 'joined')
            ->update(['attendance_status' => 'left']);
    }

    public function cancelMeeting(string $reason = null): void
    {
        $metadata = $this->integration_metadata ?? [];
        if ($reason) {
            $metadata['cancellation_reason'] = $reason;
            $metadata['cancelled_at'] = now()->toISOString();
        }

        $this->update([
            'status' => 'cancelled',
            'integration_metadata' => $metadata,
        ]);
    }

    public function generateJoinUrl(): string
    {
        if ($this->join_url) {
            return $this->join_url;
        }

        // Generate join URL based on provider
        switch ($this->meeting_provider) {
            case 'livekit':
                return route('meetings.join', ['meeting' => $this->id]);
            default:
                return $this->join_url ?? '';
        }
    }

    public function generateHostUrl(): string
    {
        if ($this->host_url) {
            return $this->host_url;
        }

        // Generate host URL based on provider
        switch ($this->meeting_provider) {
            case 'livekit':
                return route('meetings.host', ['meeting' => $this->id]);
            default:
                return $this->host_url ?? $this->generateJoinUrl();
        }
    }

    public function addAttendee(string $email, string $name = null, string $role = 'attendee'): MeetingAttendee
    {
        return $this->attendees()->create([
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'invitation_status' => 'pending',
            'attendance_status' => 'not_joined',
            'invited_at' => now(),
        ]);
    }

    public function addReminder(User $user, int $minutesBefore, string $type = 'notification'): MeetingReminder
    {
        $scheduledAt = $this->calendarEvent->starts_at->subMinutes($minutesBefore);

        return $this->reminders()->create([
            'user_id' => $user->id,
            'minutes_before' => $minutesBefore,
            'reminder_type' => $type,
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function getExpectedParticipants(): array
    {
        return $this->participant_roster ?? [];
    }

    public function updateMeetingSettings(array $settings): void
    {
        $this->update([
            'meeting_settings' => array_merge($this->meeting_settings ?? [], $settings),
        ]);
    }

    public function getMeetingInfo(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->meeting_type,
            'provider' => $this->meeting_provider,
            'status' => $this->status,
            'join_url' => $this->generateJoinUrl(),
            'host_url' => $this->generateHostUrl(),
            'has_password' => !empty($this->meeting_password),
            'e2ee_enabled' => $this->e2ee_enabled,
            'recording_enabled' => $this->recording_enabled,
            'auto_join_enabled' => $this->auto_join_enabled,
            'attendee_count' => $this->getAttendeeCount(),
            'active_attendee_count' => $this->getActiveAttendeeCount(),
            'duration_minutes' => $this->getMeetingDuration(),
            'calendar_event' => [
                'title' => $this->calendarEvent->title,
                'starts_at' => $this->calendarEvent->starts_at,
                'ends_at' => $this->calendarEvent->ends_at,
            ],
        ];
    }

    // Security Methods
    public function createDefaultSecurityControl(): MeetingSecurityControl
    {
        return $this->securityControl()->create([
            'created_by' => auth()->id() ?? $this->attendees()->where('role', 'host')->first()?->user_id,
            'require_authentication' => true,
            'enable_waiting_room' => true,
            'require_host_approval' => true,
            'enable_end_to_end_encryption' => $this->e2ee_enabled,
            'recording_permission' => $this->recording_enabled ? 'host_only' : 'disabled',
            'monitor_suspicious_activity' => true,
            'log_all_participant_actions' => true,
            'enable_audit_trail' => true,
        ]);
    }

    public function getSecurityControl(): MeetingSecurityControl
    {
        return $this->securityControl ?? $this->createDefaultSecurityControl();
    }

    public function canUserJoin(User $user, string $password = null, string $email = null): array
    {
        $securityControl = $this->getSecurityControl();
        $result = [
            'can_join' => false,
            'reasons' => [],
            'requires_approval' => false,
            'requires_password' => false,
        ];

        // Check if meeting is active
        if (!in_array($this->status, ['scheduled', 'active'])) {
            $result['reasons'][] = 'Meeting is not active';
            return $result;
        }

        // Check password if required
        if ($securityControl->require_meeting_password) {
            $result['requires_password'] = true;
            if (!$password || !$securityControl->verifyMeetingPassword($password)) {
                $result['reasons'][] = 'Invalid meeting password';
                
                // Log failed password attempt
                $securityControl->logSecurityEvent('password_failure', [
                    'user_id' => $user->id,
                    'ip_address' => request()->ip(),
                ], 'medium', 'access_control');
                
                return $result;
            }
        }

        // Check basic access permissions
        if (!$securityControl->canUserJoin($user, $email)) {
            $result['reasons'][] = 'Access denied by security policy';
            
            // Log unauthorized access attempt
            $securityControl->logSecurityEvent('unauthorized_access', [
                'user_id' => $user->id,
                'email' => $email,
                'reason' => 'security_policy_violation',
            ], 'high', 'access_control');
            
            return $result;
        }

        // Check if approval is required
        if ($securityControl->require_host_approval || $securityControl->enable_waiting_room) {
            $result['requires_approval'] = true;
        }

        // Check for suspicious activity
        $securityControl->detectSuspiciousActivity([
            'user_id' => $user->id,
            'action' => 'join_attempt',
        ]);

        $result['can_join'] = true;
        return $result;
    }

    public function lockMeeting(User $user = null): bool
    {
        $securityControl = $this->getSecurityControl();
        
        // Check if user has permission to lock meeting
        if ($user && !$this->canUserLockMeeting($user)) {
            $securityControl->logSecurityEvent('permission_violation', [
                'user_id' => $user->id,
                'action' => 'lock_meeting',
            ], 'medium', 'access_control');
            
            return false;
        }

        $this->update(['status' => 'locked']);
        
        $securityControl->logSecurityEvent('meeting_locked', [
            'locked_by' => $user?->id,
        ], 'low', 'access_control');

        return true;
    }

    public function unlockMeeting(User $user = null): bool
    {
        $securityControl = $this->getSecurityControl();
        
        // Check if user has permission to unlock meeting
        if ($user && !$this->canUserLockMeeting($user)) {
            $securityControl->logSecurityEvent('permission_violation', [
                'user_id' => $user->id,
                'action' => 'unlock_meeting',
            ], 'medium', 'access_control');
            
            return false;
        }

        $this->update(['status' => 'active']);
        
        $securityControl->logSecurityEvent('meeting_unlocked', [
            'unlocked_by' => $user?->id,
        ], 'low', 'access_control');

        return true;
    }

    protected function canUserLockMeeting(User $user): bool
    {
        // Host can always lock/unlock
        $hostAttendee = $this->attendees()->where('user_id', $user->id)->where('role', 'host')->first();
        if ($hostAttendee) {
            return true;
        }

        // Check for moderator permissions
        $moderatorAttendee = $this->attendees()->where('user_id', $user->id)->where('role', 'moderator')->first();
        return (bool) $moderatorAttendee;
    }

    public function generateSecureJoinUrl(User $user = null, array $permissions = []): string
    {
        $securityControl = $this->getSecurityControl();
        $token = $securityControl->generateAccessToken('join_link', $user, $permissions, 24);
        
        return route('meeting.join', [
            'meeting' => $this->id,
            'token' => $token,
        ]);
    }

    public function getSecuritySummary(): array
    {
        $securityControl = $this->getSecurityControl();
        
        return [
            'authentication_required' => $securityControl->require_authentication,
            'password_protected' => $securityControl->require_meeting_password,
            'waiting_room_enabled' => $securityControl->enable_waiting_room,
            'host_approval_required' => $securityControl->require_host_approval,
            'e2e_encryption' => $securityControl->enable_end_to_end_encryption,
            'participant_limit' => $securityControl->max_participants,
            'domain_restrictions' => !empty($securityControl->allowed_domains) || !empty($securityControl->blocked_domains),
            'user_restrictions' => !empty($securityControl->allowed_users) || !empty($securityControl->blocked_users),
            'recording_permission' => $securityControl->recording_permission,
            'monitoring_enabled' => $securityControl->monitor_suspicious_activity,
            'audit_trail_enabled' => $securityControl->enable_audit_trail,
            'recent_security_events' => $securityControl->securityEvents()->where('created_at', '>=', now()->subDay())->count(),
        ];
    }

    // Layout Management Methods
    public function getDefaultLayout(): ?MeetingRoomLayout
    {
        return $this->layouts()->where('is_default', true)->first() ?? $this->createDefaultLayout();
    }

    public function createDefaultLayout(): MeetingRoomLayout
    {
        return $this->layouts()->create([
            'created_by' => auth()->id() ?? $this->attendees()->where('role', 'host')->first()?->user_id,
            'layout_name' => 'Default Grid Layout',
            'description' => 'Auto-generated default layout for this meeting',
            'layout_type' => 'grid',
            'is_default' => true,
            'is_active' => true,
            'layout_config' => [
                'auto_generated' => true,
                'created_at' => now()->toISOString(),
            ],
            'auto_arrange_participants' => true,
            'max_visible_participants' => 25,
            'highlight_active_speaker' => true,
            'show_participant_names' => true,
            'show_participant_status' => true,
            'grid_aspect_ratio' => '16:9',
            'fill_grid_dynamically' => true,
            'enable_layout_switching' => true,
            'allow_participant_pinning' => true,
            'enable_spotlight_mode' => true,
            'show_layout_controls' => true,
        ]);
    }

    public function switchLayout(string $layoutId, User $user = null): array
    {
        $newLayout = $this->layouts()->where('id', $layoutId)->first();
        
        if (!$newLayout) {
            return [
                'success' => false,
                'message' => 'Layout not found',
            ];
        }

        $currentLayout = $this->getDefaultLayout();
        
        if ($currentLayout && !$currentLayout->switchToLayout($newLayout, $user)) {
            return [
                'success' => false,
                'message' => 'Failed to switch layouts',
            ];
        }

        // Make the new layout default
        $newLayout->makeDefault();

        return [
            'success' => true,
            'layout_config' => $newLayout->getLayoutConfiguration(),
            'message' => "Switched to {$newLayout->layout_name}",
        ];
    }

    public function arrangeParticipants(array $participantIds = null, array $customArrangement = null): array
    {
        $layout = $this->getDefaultLayout();
        
        if (!$layout) {
            return [];
        }

        // Use actual attendees if no specific participant IDs provided
        if ($participantIds === null) {
            $participantIds = $this->attendees()
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->toArray();
        }

        return $layout->arrangeParticipants($participantIds, $customArrangement);
    }

    public function applyLayoutPreset(string $presetId): array
    {
        $preset = MeetingLayoutPreset::find($presetId);
        
        if (!$preset) {
            return [
                'success' => false,
                'message' => 'Preset not found',
            ];
        }

        // Check if user can use this preset
        if (!$preset->is_public && $preset->organization_id !== $this->getOrganizationId()) {
            return [
                'success' => false,
                'message' => 'Access denied to this preset',
            ];
        }

        // Create layout from preset
        $layout = MeetingRoomLayout::createFromPreset($preset, $this->id);
        $layout->makeDefault();

        // Increment preset usage count
        $preset->increment('usage_count');

        return [
            'success' => true,
            'layout_config' => $layout->getLayoutConfiguration(),
            'message' => "Applied preset: {$preset->preset_name}",
        ];
    }

    public function getLayoutSummary(): array
    {
        $defaultLayout = $this->getDefaultLayout();
        $allLayouts = $this->layouts()->where('is_active', true)->get();

        return [
            'current_layout' => $defaultLayout ? [
                'id' => $defaultLayout->id,
                'name' => $defaultLayout->layout_name,
                'type' => $defaultLayout->layout_type,
                'is_custom' => $defaultLayout->layout_type === 'custom',
            ] : null,
            'available_layouts' => $allLayouts->map(function ($layout) {
                return [
                    'id' => $layout->id,
                    'name' => $layout->layout_name,
                    'type' => $layout->layout_type,
                    'is_default' => $layout->is_default,
                ];
            })->toArray(),
            'layout_features' => [
                'can_switch' => $defaultLayout?->enable_layout_switching ?? true,
                'can_pin_participants' => $defaultLayout?->allow_participant_pinning ?? true,
                'has_spotlight_mode' => $defaultLayout?->enable_spotlight_mode ?? true,
                'shows_controls' => $defaultLayout?->show_layout_controls ?? true,
            ],
            'customization_options' => [
                'background_customization' => true,
                'branding_supported' => true,
                'responsive_layouts' => true,
                'accessibility_options' => true,
            ],
        ];
    }

    protected function getOrganizationId(): ?string
    {
        // This would depend on how organization is linked to meetings
        // Assuming it's through the calendar event or attendees
        return $this->calendarEvent?->user?->organizations()?->first()?->id;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'meeting_type', 'meeting_provider', 'e2ee_enabled', 'recording_enabled'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Meeting integration {$eventName}")
            ->useLogName('meeting_integration');
    }
}