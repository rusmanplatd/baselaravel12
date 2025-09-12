<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingReminder extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'meeting_integration_id',
        'user_id',
        'minutes_before',
        'reminder_type',
        'status',
        'scheduled_at',
        'sent_at',
        'failure_reason',
        'reminder_data',
    ];

    protected $casts = [
        'minutes_before' => 'integer',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'reminder_data' => 'array',
    ];

    // Relationships
    public function meetingIntegration(): BelongsTo
    {
        return $this->belongsTo(MeetingCalendarIntegration::class, 'meeting_integration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('reminder_type', $type);
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeUpcoming($query, int $minutesAhead = 60)
    {
        return $query->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now(), now()->addMinutes($minutesAhead)]);
    }

    // Helper methods
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isDue(): bool
    {
        return $this->isScheduled() && $this->scheduled_at <= now();
    }

    public function isNotification(): bool
    {
        return $this->reminder_type === 'notification';
    }

    public function isEmail(): bool
    {
        return $this->reminder_type === 'email';
    }

    public function isSms(): bool
    {
        return $this->reminder_type === 'sms';
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    public function reschedule(int $minutesFromNow): void
    {
        $this->update([
            'scheduled_at' => now()->addMinutes($minutesFromNow),
            'status' => 'scheduled',
        ]);
    }

    public function updateReminderData(array $data): void
    {
        $this->update([
            'reminder_data' => array_merge($this->reminder_data ?? [], $data),
        ]);
    }

    public function getFormattedTimeUntilMeeting(): string
    {
        $meeting = $this->meetingIntegration->calendarEvent;
        $diffInMinutes = now()->diffInMinutes($meeting->starts_at, false);

        if ($diffInMinutes < 0) {
            return 'Meeting has started';
        }

        if ($diffInMinutes < 60) {
            return "{$diffInMinutes} minutes";
        }

        $hours = floor($diffInMinutes / 60);
        $minutes = $diffInMinutes % 60;

        if ($hours < 24) {
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        return $remainingHours > 0 ? "{$days}d {$remainingHours}h" : "{$days}d";
    }

    public function getReminderMessage(): array
    {
        $meeting = $this->meetingIntegration->calendarEvent;
        $timeUntil = $this->getFormattedTimeUntilMeeting();

        $baseMessage = [
            'title' => "Meeting Reminder: {$meeting->title}",
            'body' => "Your meeting \"{$meeting->title}\" starts in {$timeUntil}",
            'meeting_title' => $meeting->title,
            'meeting_time' => $meeting->starts_at->format('M j, Y g:i A'),
            'time_until' => $timeUntil,
            'join_url' => $this->meetingIntegration->generateJoinUrl(),
        ];

        if ($meeting->location) {
            $baseMessage['location'] = $meeting->location;
        }

        if ($this->meetingIntegration->meeting_password) {
            $baseMessage['has_password'] = true;
        }

        return array_merge($baseMessage, $this->reminder_data ?? []);
    }

    public function getReminderInfo(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->reminder_type,
            'status' => $this->status,
            'minutes_before' => $this->minutes_before,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'is_due' => $this->isDue(),
            'failure_reason' => $this->failure_reason,
            'user_id' => $this->user_id,
            'meeting_title' => $this->meetingIntegration->calendarEvent->title,
        ];
    }
}