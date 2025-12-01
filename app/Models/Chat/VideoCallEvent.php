<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoCallEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'video_call_id',
        'user_id',
        'event_type',
        'event_data',
        'event_timestamp',
    ];

    protected $casts = [
        'event_data' => 'array',
        'event_timestamp' => 'datetime',
    ];

    // Relationships
    public function videoCall(): BelongsTo
    {
        return $this->belongsTo(VideoCall::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeParticipantEvents($query)
    {
        return $query->whereIn('event_type', [
            'participant_joined',
            'participant_left',
            'participant_disconnected',
            'participant_reconnected',
        ]);
    }

    public function scopeTrackEvents($query)
    {
        return $query->whereIn('event_type', [
            'track_published',
            'track_unpublished',
            'track_subscribed',
            'track_unsubscribed',
        ]);
    }

    public function scopeQualityEvents($query)
    {
        return $query->whereIn('event_type', [
            'quality_changed',
            'connection_quality_changed',
            'bandwidth_changed',
        ]);
    }

    // Helper methods
    public function isParticipantEvent(): bool
    {
        return in_array($this->event_type, [
            'participant_joined',
            'participant_left',
            'participant_disconnected',
            'participant_reconnected',
        ]);
    }

    public function isTrackEvent(): bool
    {
        return in_array($this->event_type, [
            'track_published',
            'track_unpublished',
            'track_subscribed',
            'track_unsubscribed',
        ]);
    }

    public function isQualityEvent(): bool
    {
        return in_array($this->event_type, [
            'quality_changed',
            'connection_quality_changed',
            'bandwidth_changed',
        ]);
    }

    public function isE2EEEvent(): bool
    {
        return in_array($this->event_type, [
            'e2ee_key_generated',
            'e2ee_key_distributed',
            'e2ee_key_rotated',
            'e2ee_encryption_failed',
        ]);
    }

    public function getEventData(?string $key = null)
    {
        if ($key === null) {
            return $this->event_data;
        }

        return $this->event_data[$key] ?? null;
    }

    public function getEventSummary(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->event_type,
            'timestamp' => $this->event_timestamp->toISOString(),
            'user_id' => $this->user_id,
            'data' => $this->event_data,
        ];
    }
}
