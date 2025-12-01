<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MeetingTemplate extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'organization_id',
        'meeting_settings',
        'participant_limits',
        'e2ee_enabled',
        'recording_enabled',
        'auto_join_enabled',
        'default_layout',
        'layout_settings',
        'background_image',
        'branding_settings',
        'auto_start_recording',
        'auto_send_reminders',
        'auto_generate_transcripts',
        'automation_rules',
        'default_attendees',
        'permission_presets',
        'enable_breakout_rooms',
        'default_breakout_count',
        'breakout_settings',
        'integration_settings',
        'webhook_settings',
        'usage_count',
        'last_used_at',
        'is_active',
        'is_public',
    ];

    protected $casts = [
        'meeting_settings' => 'array',
        'participant_limits' => 'array',
        'e2ee_enabled' => 'boolean',
        'recording_enabled' => 'boolean',
        'auto_join_enabled' => 'boolean',
        'layout_settings' => 'array',
        'branding_settings' => 'array',
        'auto_start_recording' => 'boolean',
        'auto_send_reminders' => 'boolean',
        'auto_generate_transcripts' => 'boolean',
        'automation_rules' => 'array',
        'default_attendees' => 'array',
        'permission_presets' => 'array',
        'enable_breakout_rooms' => 'boolean',
        'default_breakout_count' => 'integer',
        'breakout_settings' => 'array',
        'integration_settings' => 'array',
        'webhook_settings' => 'array',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            MeetingTemplateCategory::class,
            'meeting_template_category_assignments',
            'template_id',
            'category_id'
        );
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(MeetingTemplateUsageLog::class, 'template_id');
    }

    public function scheduledSeries(): HasMany
    {
        return $this->hasMany(ScheduledMeetingSeries::class, 'template_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('meeting_template_categories.id', $categoryId);
        });
    }

    public function scopePopular($query, $limit = 10)
    {
        return $query->orderByDesc('usage_count')->limit($limit);
    }

    public function scopeRecentlyUsed($query, $limit = 10)
    {
        return $query->whereNotNull('last_used_at')
            ->orderByDesc('last_used_at')
            ->limit($limit);
    }

    // Helper Methods
    public function isUsableBy(User $user): bool
    {
        // Template creator can always use it
        if ($this->created_by === $user->id) {
            return true;
        }

        // Public templates in same organization
        if ($this->is_public && $this->organization_id) {
            return $user->organizations()->where('organizations.id', $this->organization_id)->exists();
        }

        // Private templates are only usable by creator
        return false;
    }

    public function getDefaultSettings(): array
    {
        return [
            'audio_enabled' => $this->meeting_settings['audio_enabled'] ?? true,
            'video_enabled' => $this->meeting_settings['video_enabled'] ?? true,
            'screen_sharing_enabled' => $this->meeting_settings['screen_sharing_enabled'] ?? true,
            'chat_enabled' => $this->meeting_settings['chat_enabled'] ?? true,
            'waiting_room_enabled' => $this->participant_limits['waiting_room_enabled'] ?? false,
            'mute_on_entry' => $this->meeting_settings['mute_on_entry'] ?? false,
            'camera_on_entry' => $this->meeting_settings['camera_on_entry'] ?? true,
            'max_participants' => $this->participant_limits['max_participants'] ?? 50,
            'recording_enabled' => $this->recording_enabled,
            'e2ee_enabled' => $this->e2ee_enabled,
            'layout' => $this->default_layout,
        ];
    }

    public function applyToMeeting(MeetingCalendarIntegration $meeting, array $overrides = []): array
    {
        $settings = $this->getDefaultSettings();
        $appliedSettings = array_merge($settings, $overrides);

        // Update meeting with template settings
        $meeting->updateMeetingSettings($appliedSettings);
        
        // Update meeting flags
        $meeting->update([
            'e2ee_enabled' => $appliedSettings['e2ee_enabled'],
            'recording_enabled' => $appliedSettings['recording_enabled'],
        ]);

        // Add default attendees if specified
        if (!empty($this->default_attendees)) {
            foreach ($this->default_attendees as $attendeeData) {
                $meeting->addAttendee(
                    $attendeeData['email'],
                    $attendeeData['name'] ?? null,
                    $attendeeData['role'] ?? 'attendee'
                );
            }
        }

        // Set up automation rules
        $this->applyAutomationRules($meeting);

        // Log usage
        $this->logUsage($meeting, $appliedSettings, $overrides);

        // Update usage statistics
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);

        return $appliedSettings;
    }

    public function createFromMeeting(MeetingCalendarIntegration $meeting, array $templateData): self
    {
        $templateSettings = array_merge([
            'name' => $templateData['name'],
            'description' => $templateData['description'] ?? null,
            'created_by' => auth()->id(),
            'organization_id' => $templateData['organization_id'] ?? null,
            'meeting_settings' => $meeting->meeting_settings ?? [],
            'participant_limits' => $meeting->participant_limits ?? [],
            'e2ee_enabled' => $meeting->e2ee_enabled,
            'recording_enabled' => $meeting->recording_enabled,
            'auto_join_enabled' => $meeting->auto_join_enabled,
            'is_public' => $templateData['is_public'] ?? false,
        ], $templateData);

        return self::create($templateSettings);
    }

    public function duplicate(array $overrides = []): self
    {
        $data = $this->toArray();
        
        // Remove unique fields
        unset($data['id'], $data['created_at'], $data['updated_at']);
        
        // Reset usage statistics
        $data['usage_count'] = 0;
        $data['last_used_at'] = null;
        
        // Apply overrides
        $data = array_merge($data, $overrides);
        
        // Create new template
        $newTemplate = self::create($data);
        
        // Copy categories
        $newTemplate->categories()->sync($this->categories->pluck('id'));
        
        return $newTemplate;
    }

    public function getTemplatePreview(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'layout' => $this->default_layout,
            'settings_summary' => [
                'participants' => $this->participant_limits['max_participants'] ?? 50,
                'recording' => $this->recording_enabled,
                'e2ee' => $this->e2ee_enabled,
                'breakout_rooms' => $this->enable_breakout_rooms,
            ],
            'usage_count' => $this->usage_count,
            'last_used' => $this->last_used_at?->diffForHumans(),
            'categories' => $this->categories->pluck('name'),
            'is_public' => $this->is_public,
        ];
    }

    public function getAutomationSummary(): array
    {
        return [
            'auto_start_recording' => $this->auto_start_recording,
            'auto_send_reminders' => $this->auto_send_reminders,
            'auto_generate_transcripts' => $this->auto_generate_transcripts,
            'automation_rules_count' => count($this->automation_rules ?? []),
            'default_attendees_count' => count($this->default_attendees ?? []),
            'breakout_rooms_enabled' => $this->enable_breakout_rooms,
        ];
    }

    protected function applyAutomationRules(MeetingCalendarIntegration $meeting): void
    {
        if (empty($this->automation_rules)) {
            return;
        }

        foreach ($this->automation_rules as $rule) {
            $this->executeAutomationRule($meeting, $rule);
        }
    }

    protected function executeAutomationRule(MeetingCalendarIntegration $meeting, array $rule): void
    {
        $type = $rule['type'] ?? null;
        
        switch ($type) {
            case 'send_reminder':
                if ($this->auto_send_reminders) {
                    $minutesBefore = $rule['minutes_before'] ?? 15;
                    $reminderType = $rule['reminder_type'] ?? 'notification';
                    
                    // Add reminder for each attendee
                    $meeting->attendees()->whereNotNull('user_id')->with('user')->get()
                        ->each(function ($attendee) use ($meeting, $minutesBefore, $reminderType) {
                            if ($attendee->user) {
                                $meeting->addReminder($attendee->user, $minutesBefore, $reminderType);
                            }
                        });
                }
                break;

            case 'create_breakout_rooms':
                if ($this->enable_breakout_rooms && ($this->default_breakout_count ?? 0) > 0) {
                    // This would be handled during meeting start
                    $meeting->updateMeetingSettings([
                        'breakout_rooms_enabled' => true,
                        'breakout_room_count' => $this->default_breakout_count,
                        'breakout_settings' => $this->breakout_settings ?? []
                    ]);
                }
                break;

            case 'apply_layout':
                if (!empty($rule['layout'])) {
                    $meeting->updateMeetingSettings([
                        'layout' => $rule['layout'],
                        'layout_settings' => $rule['layout_settings'] ?? []
                    ]);
                }
                break;
        }
    }

    protected function logUsage(
        MeetingCalendarIntegration $meeting,
        array $appliedSettings,
        array $modifiedSettings
    ): void {
        MeetingTemplateUsageLog::create([
            'template_id' => $this->id,
            'meeting_id' => $meeting->id,
            'used_by' => auth()->id(),
            'applied_settings' => $appliedSettings,
            'modified_settings' => $modifiedSettings,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'is_active', 'is_public', 'meeting_settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Meeting template {$eventName}")
            ->useLogName('meeting_template');
    }
}