<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CalendarEvent extends Model
{
    use HasFactory, HasUlids, SoftDeletes, LogsActivity;

    protected $fillable = [
        'calendar_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'is_all_day',
        'location',
        'color',
        'status',
        'visibility',
        'recurrence_rule',
        'recurrence_parent_id',
        'attendees',
        'reminders',
        'metadata',
        'meeting_url',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_all_day' => 'boolean',
        'attendees' => 'array',
        'reminders' => 'array',
        'metadata' => 'array',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'recurrence_parent_id');
    }

    public function recurrenceInstances(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'recurrence_parent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function meetingIntegration(): HasOne
    {
        return $this->hasOne(MeetingCalendarIntegration::class);
    }

    public function scopeInDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('starts_at', [$start, $end])
                ->orWhereBetween('ends_at', [$start, $end])
                ->orWhere(function ($innerQ) use ($start, $end) {
                    $innerQ->where('starts_at', '<=', $start)
                        ->where('ends_at', '>=', $end);
                });
        });
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now());
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeRecurring($query)
    {
        return $query->whereNotNull('recurrence_rule');
    }

    public function scopeNonRecurring($query)
    {
        return $query->whereNull('recurrence_rule');
    }

    public function canView(User $user): bool
    {
        if ($this->visibility === 'public') {
            return true;
        }

        return $this->calendar->canView($user);
    }

    public function canEdit(User $user): bool
    {
        if ($this->created_by === $user->id) {
            return true;
        }

        return $this->calendar->canEdit($user);
    }

    public function isRecurring(): bool
    {
        return !is_null($this->recurrence_rule);
    }

    public function isRecurrenceInstance(): bool
    {
        return !is_null($this->recurrence_parent_id);
    }

    public function isAllDay(): bool
    {
        return $this->is_all_day;
    }

    public function isMultiDay(): bool
    {
        if ($this->is_all_day) {
            return $this->starts_at->toDateString() !== $this->ends_at->toDateString();
        }

        return $this->starts_at->toDateString() !== $this->ends_at->toDateString();
    }

    public function getDurationInMinutes(): int
    {
        if (!$this->ends_at) {
            return 0;
        }

        return $this->starts_at->diffInMinutes($this->ends_at);
    }

    public function hasConflictWith(CalendarEvent $other): bool
    {
        if ($other->id === $this->id) {
            return false;
        }

        if ($this->calendar_id !== $other->calendar_id) {
            return false;
        }

        $thisStart = $this->starts_at;
        $thisEnd = $this->ends_at ?? $this->starts_at->copy()->addHour();
        $otherStart = $other->starts_at;
        $otherEnd = $other->ends_at ?? $other->starts_at->copy()->addHour();

        return $thisStart->lt($otherEnd) && $thisEnd->gt($otherStart);
    }

    public function addReminder(int $minutesBefore, string $method = 'popup'): void
    {
        $reminders = $this->reminders ?? [];
        $reminders[] = [
            'minutes' => $minutesBefore,
            'method' => $method,
        ];
        $this->update(['reminders' => $reminders]);
    }

    public function addAttendee(string $email, string $name = null): void
    {
        $attendees = $this->attendees ?? [];
        $attendees[] = [
            'email' => $email,
            'name' => $name,
            'status' => 'pending',
            'added_at' => now()->toISOString(),
        ];
        $this->update(['attendees' => $attendees]);
    }

    public function updateAttendeeStatus(string $email, string $status): void
    {
        $attendees = $this->attendees ?? [];
        
        foreach ($attendees as &$attendee) {
            if ($attendee['email'] === $email) {
                $attendee['status'] = $status;
                $attendee['responded_at'] = now()->toISOString();
                break;
            }
        }
        
        $this->update(['attendees' => $attendees]);
    }

    public function getDisplayColorAttribute(): string
    {
        return $this->color ?? $this->calendar->color ?? '#3498db';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'starts_at', 'ends_at', 'status', 'visibility'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Calendar event {$eventName}")
            ->useLogName('calendar_event');
    }
}
