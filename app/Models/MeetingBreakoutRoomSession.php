<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingBreakoutRoomSession extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'breakout_room_id',
        'session_number',
        'started_at',
        'ended_at',
        'duration_minutes',
        'max_participants',
        'avg_participants',
        'participant_timeline',
        'total_messages',
        'screen_shares',
        'recording_enabled',
        'recording_url',
        'quality_metrics',
        'technical_issues',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'session_number' => 'integer',
        'max_participants' => 'integer',
        'avg_participants' => 'integer',
        'participant_timeline' => 'array',
        'total_messages' => 'integer',
        'screen_shares' => 'integer',
        'recording_enabled' => 'boolean',
        'quality_metrics' => 'array',
        'technical_issues' => 'array',
    ];

    // Relationships
    public function breakoutRoom(): BelongsTo
    {
        return $this->belongsTo(MeetingBreakoutRoom::class, 'breakout_room_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('ended_at');
    }

    public function scopeByRoom($query, string $breakoutRoomId)
    {
        return $query->where('breakout_room_id', $breakoutRoomId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    // Helper Methods
    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }

    public function endSession(): void
    {
        $duration = $this->started_at->diffInMinutes(now());

        $this->update([
            'ended_at' => now(),
            'duration_minutes' => $duration,
        ]);

        $this->calculateSessionMetrics();
    }

    public function getCurrentDuration(): int
    {
        if ($this->ended_at) {
            return $this->duration_minutes ?? 0;
        }

        return $this->started_at->diffInMinutes(now());
    }

    public function addParticipantEvent(string $event, string $attendeeId, array $metadata = []): void
    {
        $timeline = $this->participant_timeline ?? [];
        
        $timeline[] = [
            'timestamp' => now()->toISOString(),
            'event' => $event, // 'joined', 'left', 'reconnected'
            'attendee_id' => $attendeeId,
            'metadata' => $metadata,
        ];

        $this->update(['participant_timeline' => $timeline]);
    }

    public function incrementMessageCount(): void
    {
        $this->increment('total_messages');
    }

    public function incrementScreenShares(): void
    {
        $this->increment('screen_shares');
    }

    public function updateQualityMetrics(array $metrics): void
    {
        $currentMetrics = $this->quality_metrics ?? [];
        
        $updatedMetrics = array_merge($currentMetrics, [
            'timestamp' => now()->toISOString(),
            'metrics' => $metrics,
        ]);

        $this->update(['quality_metrics' => $updatedMetrics]);
    }

    public function addTechnicalIssue(string $issueType, array $details): void
    {
        $issues = $this->technical_issues ?? [];
        
        $issues[] = [
            'timestamp' => now()->toISOString(),
            'type' => $issueType,
            'details' => $details,
        ];

        $this->update(['technical_issues' => $issues]);
    }

    protected function calculateSessionMetrics(): void
    {
        $timeline = $this->participant_timeline ?? [];
        
        if (empty($timeline)) {
            return;
        }

        // Calculate max participants
        $participantCounts = [];
        $currentParticipants = [];
        
        foreach ($timeline as $event) {
            $timestamp = $event['timestamp'];
            
            if ($event['event'] === 'joined') {
                $currentParticipants[$event['attendee_id']] = $timestamp;
            } elseif ($event['event'] === 'left') {
                unset($currentParticipants[$event['attendee_id']]);
            }
            
            $participantCounts[] = count($currentParticipants);
        }

        $maxParticipants = max($participantCounts);
        $avgParticipants = round(array_sum($participantCounts) / count($participantCounts));

        $this->update([
            'max_participants' => $maxParticipants,
            'avg_participants' => $avgParticipants,
        ]);
    }

    public function getSessionSummary(): array
    {
        return [
            'session_number' => $this->session_number,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'duration_minutes' => $this->getCurrentDuration(),
            'is_active' => $this->isActive(),
            'participants' => [
                'max' => $this->max_participants,
                'avg' => $this->avg_participants,
            ],
            'activity' => [
                'total_messages' => $this->total_messages,
                'screen_shares' => $this->screen_shares,
                'recording_enabled' => $this->recording_enabled,
            ],
            'quality' => [
                'has_quality_data' => !empty($this->quality_metrics),
                'technical_issues_count' => count($this->technical_issues ?? []),
            ],
            'room' => [
                'id' => $this->breakoutRoom->id,
                'display_name' => $this->breakoutRoom->display_name,
                'room_number' => $this->breakoutRoom->room_number,
            ],
        ];
    }

    public function getParticipantActivity(): array
    {
        $timeline = $this->participant_timeline ?? [];
        $participants = [];

        foreach ($timeline as $event) {
            $attendeeId = $event['attendee_id'];
            
            if (!isset($participants[$attendeeId])) {
                $participants[$attendeeId] = [
                    'attendee_id' => $attendeeId,
                    'join_count' => 0,
                    'total_time' => 0,
                    'events' => [],
                ];
            }

            $participants[$attendeeId]['events'][] = $event;
            
            if ($event['event'] === 'joined') {
                $participants[$attendeeId]['join_count']++;
            }
        }

        // Calculate total time for each participant
        foreach ($participants as $attendeeId => &$participant) {
            $totalTime = 0;
            $joinTime = null;

            foreach ($participant['events'] as $event) {
                if ($event['event'] === 'joined') {
                    $joinTime = new \DateTime($event['timestamp']);
                } elseif ($event['event'] === 'left' && $joinTime) {
                    $leaveTime = new \DateTime($event['timestamp']);
                    $totalTime += $joinTime->diffInMinutes($leaveTime);
                    $joinTime = null;
                }
            }

            // If still in room, calculate time until now
            if ($joinTime) {
                $totalTime += $joinTime->diffInMinutes(now());
            }

            $participant['total_time'] = $totalTime;
        }

        return array_values($participants);
    }
}