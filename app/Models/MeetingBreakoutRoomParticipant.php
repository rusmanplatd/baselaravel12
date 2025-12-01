<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingBreakoutRoomParticipant extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'breakout_room_id',
        'attendee_id',
        'assignment_type',
        'status',
        'assigned_at',
        'joined_at',
        'left_at',
        'duration_minutes',
        'assigned_by',
        'assignment_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    // Relationships
    public function breakoutRoom(): BelongsTo
    {
        return $this->belongsTo(MeetingBreakoutRoom::class, 'breakout_room_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(MeetingAttendee::class, 'attendee_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'joined');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByAssignmentType($query, string $type)
    {
        return $query->where('assignment_type', $type);
    }

    public function scopeInRoom($query, string $breakoutRoomId)
    {
        return $query->where('breakout_room_id', $breakoutRoomId);
    }

    // Helper Methods
    public function join(): void
    {
        $this->update([
            'status' => 'joined',
            'joined_at' => now(),
        ]);

        $this->breakoutRoom->updateParticipantCount();
    }

    public function leave(): void
    {
        $duration = null;
        if ($this->joined_at) {
            $duration = $this->joined_at->diffInMinutes(now());
        }

        $this->update([
            'status' => 'left',
            'left_at' => now(),
            'duration_minutes' => $duration,
        ]);

        $this->breakoutRoom->updateParticipantCount();
    }

    public function move(MeetingBreakoutRoom $targetRoom): void
    {
        $this->leave();
        
        $targetRoom->addParticipant(
            $this->attendee_id,
            'manual',
            $this->assigned_by
        );
    }

    public function getDurationMinutes(): ?int
    {
        if ($this->status === 'joined' && $this->joined_at) {
            return $this->joined_at->diffInMinutes(now());
        }

        return $this->duration_minutes;
    }

    public function isCurrentlyInRoom(): bool
    {
        return $this->status === 'joined';
    }

    public function hasJoinedRoom(): bool
    {
        return in_array($this->status, ['joined', 'left']);
    }

    public function getParticipationSummary(): array
    {
        return [
            'status' => $this->status,
            'assignment_type' => $this->assignment_type,
            'assigned_at' => $this->assigned_at,
            'joined_at' => $this->joined_at,
            'left_at' => $this->left_at,
            'duration_minutes' => $this->getDurationMinutes(),
            'is_currently_in_room' => $this->isCurrentlyInRoom(),
            'has_joined_room' => $this->hasJoinedRoom(),
            'attendee' => [
                'id' => $this->attendee->id,
                'user_name' => $this->attendee->user?->name,
                'email' => $this->attendee->email,
                'role' => $this->attendee->role,
            ],
            'room' => [
                'id' => $this->breakoutRoom->id,
                'display_name' => $this->breakoutRoom->display_name,
                'room_number' => $this->breakoutRoom->room_number,
            ],
        ];
    }
}