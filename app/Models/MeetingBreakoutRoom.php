<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MeetingBreakoutRoom extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'meeting_id',
        'room_name',
        'room_sid',
        'display_name',
        'description',
        'room_number',
        'max_participants',
        'current_participants',
        'status',
        'opened_at',
        'closed_at',
        'duration_minutes',
        'room_settings',
        'auto_assign',
        'allow_return_main',
        'moderator_can_join',
        'created_by',
        'assigned_participants',
    ];

    protected $casts = [
        'room_settings' => 'array',
        'assigned_participants' => 'array',
        'auto_assign' => 'boolean',
        'allow_return_main' => 'boolean',
        'moderator_can_join' => 'boolean',
        'max_participants' => 'integer',
        'current_participants' => 'integer',
        'room_number' => 'integer',
        'duration_minutes' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
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

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingBreakoutRoomParticipant::class, 'breakout_room_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(MeetingBreakoutRoomSession::class, 'breakout_room_id');
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()->where('status', 'joined');
    }

    public function currentSession(): HasMany
    {
        return $this->sessions()->whereNull('ended_at')->latest();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByMeeting($query, $meetingId)
    {
        return $query->where('meeting_id', $meetingId);
    }

    public function scopeAvailable($query)
    {
        return $query->whereIn('status', ['created', 'active'])
            ->whereRaw('current_participants < max_participants');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('room_number');
    }

    // Helper Methods
    public function canJoin(): bool
    {
        return $this->status === 'active' && 
               $this->current_participants < $this->max_participants;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isFull(): bool
    {
        return $this->current_participants >= $this->max_participants;
    }

    public function openRoom(): void
    {
        $this->update([
            'status' => 'active',
            'opened_at' => now(),
        ]);

        // Start a new session
        $this->sessions()->create([
            'session_number' => $this->sessions()->count() + 1,
            'started_at' => now(),
        ]);
    }

    public function closeRoom(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
            'duration_minutes' => $this->opened_at ? 
                $this->opened_at->diffInMinutes(now()) : null,
        ]);

        // End current session
        $currentSession = $this->currentSession()->first();
        if ($currentSession) {
            $currentSession->endSession();
        }

        // Move all participants back to main room
        $this->participants()
            ->where('status', 'joined')
            ->update(['status' => 'left', 'left_at' => now()]);
    }

    public function addParticipant(string $attendeeId, string $assignmentType = 'manual', ?string $assignedBy = null): MeetingBreakoutRoomParticipant
    {
        // Remove from other breakout rooms in this meeting
        $this->meeting->breakoutRooms()->whereHas('participants', function ($query) use ($attendeeId) {
            $query->where('attendee_id', $attendeeId)
                ->whereIn('status', ['assigned', 'joined']);
        })->get()->each(function ($room) use ($attendeeId) {
            $room->removeParticipant($attendeeId);
        });

        return $this->participants()->create([
            'attendee_id' => $attendeeId,
            'assignment_type' => $assignmentType,
            'assigned_by' => $assignedBy ?: auth()->id(),
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);
    }

    public function removeParticipant(string $attendeeId): void
    {
        $participant = $this->participants()
            ->where('attendee_id', $attendeeId)
            ->whereIn('status', ['assigned', 'joined'])
            ->first();

        if ($participant) {
            $participant->leave();
        }
    }

    public function moveParticipant(string $attendeeId, MeetingBreakoutRoom $targetRoom): void
    {
        $this->removeParticipant($attendeeId);
        $targetRoom->addParticipant($attendeeId, 'manual');
    }

    public function updateParticipantCount(): void
    {
        $this->update([
            'current_participants' => $this->activeParticipants()->count()
        ]);
    }

    public function assignParticipantsAutomatically(Collection $attendeeIds): void
    {
        if (!$this->auto_assign) {
            return;
        }

        foreach ($attendeeIds as $attendeeId) {
            if (!$this->isFull()) {
                $this->addParticipant($attendeeId, 'automatic');
            }
        }
    }

    public function getRoomSettings(): array
    {
        $defaultSettings = [
            'audio_enabled' => true,
            'video_enabled' => true,
            'screen_sharing_enabled' => true,
            'chat_enabled' => true,
            'recording_enabled' => false,
            'max_video_quality' => 'hd',
            'background_blur' => false,
        ];

        return array_merge($defaultSettings, $this->room_settings ?? []);
    }

    public function updateRoomSettings(array $settings): void
    {
        $currentSettings = $this->getRoomSettings();
        $newSettings = array_merge($currentSettings, $settings);
        
        $this->update(['room_settings' => $newSettings]);
    }

    public function getParticipantSummary(): array
    {
        return [
            'total_assigned' => $this->participants()->count(),
            'currently_joined' => $this->activeParticipants()->count(),
            'max_capacity' => $this->max_participants,
            'available_slots' => $this->max_participants - $this->current_participants,
            'is_full' => $this->isFull(),
            'can_join' => $this->canJoin(),
        ];
    }

    public function getActivitySummary(): array
    {
        $currentSession = $this->currentSession()->first();
        $allSessions = $this->sessions()->get();

        return [
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'opened_at' => $this->opened_at,
            'duration_minutes' => $this->duration_minutes,
            'current_session' => $currentSession ? [
                'started_at' => $currentSession->started_at,
                'duration_minutes' => $currentSession->started_at->diffInMinutes(now()),
                'participant_count' => $this->current_participants,
            ] : null,
            'total_sessions' => $allSessions->count(),
            'total_participants_served' => $this->participants()->distinct('attendee_id')->count(),
        ];
    }

    public function generateRoomName(string $meetingRoomName): string
    {
        return "{$meetingRoomName}_breakout_{$this->room_number}";
    }

    public static function createMultipleForMeeting(
        MeetingCalendarIntegration $meeting, 
        int $numberOfRooms, 
        array $roomConfig = []
    ): Collection {
        $rooms = collect();
        
        for ($i = 1; $i <= $numberOfRooms; $i++) {
            $roomData = array_merge([
                'meeting_id' => $meeting->id,
                'room_number' => $i,
                'display_name' => "Breakout Room {$i}",
                'max_participants' => $roomConfig['max_participants'] ?? 10,
                'room_settings' => $roomConfig['room_settings'] ?? [],
                'auto_assign' => $roomConfig['auto_assign'] ?? false,
                'allow_return_main' => $roomConfig['allow_return_main'] ?? true,
                'moderator_can_join' => $roomConfig['moderator_can_join'] ?? true,
                'created_by' => auth()->id(),
            ], $roomConfig);

            $roomData['room_name'] = self::generateRoomNameStatic($meeting->room_name, $i);

            $room = self::create($roomData);
            $rooms->push($room);
        }

        return $rooms;
    }

    protected static function generateRoomNameStatic(string $meetingRoomName, int $roomNumber): string
    {
        return "{$meetingRoomName}_breakout_{$roomNumber}";
    }
}