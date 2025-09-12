<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendee extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'meeting_integration_id',
        'user_id',
        'email',
        'name',
        'role',
        'invitation_status',
        'attendance_status',
        'invited_at',
        'responded_at',
        'joined_at',
        'left_at',
        'duration_minutes',
        'attendee_metadata',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'responded_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'duration_minutes' => 'integer',
        'attendee_metadata' => 'array',
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
    public function scopePending($query)
    {
        return $query->where('invitation_status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('invitation_status', 'accepted');
    }

    public function scopeDeclined($query)
    {
        return $query->where('invitation_status', 'declined');
    }

    public function scopeJoined($query)
    {
        return $query->where('attendance_status', 'joined');
    }

    public function scopeLeft($query)
    {
        return $query->where('attendance_status', 'left');
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeHosts($query)
    {
        return $query->where('role', 'host');
    }

    public function scopeCoHosts($query)
    {
        return $query->where('role', 'co-host');
    }

    public function scopePresenters($query)
    {
        return $query->where('role', 'presenter');
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->invitation_status === 'pending';
    }

    public function hasAccepted(): bool
    {
        return $this->invitation_status === 'accepted';
    }

    public function hasDeclined(): bool
    {
        return $this->invitation_status === 'declined';
    }

    public function isTentative(): bool
    {
        return $this->invitation_status === 'tentative';
    }

    public function hasJoined(): bool
    {
        return $this->attendance_status === 'joined';
    }

    public function hasLeft(): bool
    {
        return $this->attendance_status === 'left';
    }

    public function wasRemoved(): bool
    {
        return $this->attendance_status === 'removed';
    }

    public function isHost(): bool
    {
        return $this->role === 'host';
    }

    public function isCoHost(): bool
    {
        return $this->role === 'co-host';
    }

    public function isPresenter(): bool
    {
        return $this->role === 'presenter';
    }

    public function isAttendee(): bool
    {
        return $this->role === 'attendee';
    }

    public function canManageMeeting(): bool
    {
        return in_array($this->role, ['host', 'co-host']);
    }

    public function canPresent(): bool
    {
        return in_array($this->role, ['host', 'co-host', 'presenter']);
    }

    public function acceptInvitation(): void
    {
        $this->update([
            'invitation_status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    public function declineInvitation(): void
    {
        $this->update([
            'invitation_status' => 'declined',
            'responded_at' => now(),
        ]);
    }

    public function markAsTentative(): void
    {
        $this->update([
            'invitation_status' => 'tentative',
            'responded_at' => now(),
        ]);
    }

    public function joinMeeting(): void
    {
        $this->update([
            'attendance_status' => 'joined',
            'joined_at' => now(),
        ]);
    }

    public function leaveMeeting(): void
    {
        $leftAt = now();
        $duration = $this->joined_at ? $this->joined_at->diffInMinutes($leftAt) : 0;

        $this->update([
            'attendance_status' => 'left',
            'left_at' => $leftAt,
            'duration_minutes' => $duration,
        ]);
    }

    public function removFromMeeting(string $reason = null): void
    {
        $metadata = $this->attendee_metadata ?? [];
        if ($reason) {
            $metadata['removal_reason'] = $reason;
            $metadata['removed_at'] = now()->toISOString();
        }

        $this->update([
            'attendance_status' => 'removed',
            'left_at' => now(),
            'attendee_metadata' => $metadata,
        ]);
    }

    public function updateRole(string $newRole): void
    {
        $this->update(['role' => $newRole]);
    }

    public function updateMetadata(array $metadata): void
    {
        $this->update([
            'attendee_metadata' => array_merge($this->attendee_metadata ?? [], $metadata),
        ]);
    }

    public function getAttendanceDuration(): ?int
    {
        return $this->duration_minutes;
    }

    public function getAttendanceDurationFormatted(): ?string
    {
        if (!$this->duration_minutes) {
            return null;
        }

        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    public function getDisplayName(): string
    {
        return $this->name ?? $this->user?->name ?? $this->email;
    }

    public function getAttendeeInfo(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->getDisplayName(),
            'role' => $this->role,
            'invitation_status' => $this->invitation_status,
            'attendance_status' => $this->attendance_status,
            'can_manage' => $this->canManageMeeting(),
            'can_present' => $this->canPresent(),
            'duration_minutes' => $this->getAttendanceDuration(),
            'duration_formatted' => $this->getAttendanceDurationFormatted(),
            'joined_at' => $this->joined_at?->toISOString(),
            'left_at' => $this->left_at?->toISOString(),
            'user_id' => $this->user_id,
        ];
    }
}