<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoCall extends Model
{
    use HasUlids;

    protected $fillable = [
        'conversation_id',
        'initiated_by',
        'livekit_room_name',
        'call_type',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'participants',
        'e2ee_settings',
        'quality_settings',
        'metadata',
        'is_recorded',
        'recording_url',
        'failure_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'participants' => 'array',
        'e2ee_settings' => 'array',
        'metadata' => 'array',
        'is_recorded' => 'boolean',
    ];

    // Relationships
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function callParticipants(): HasMany
    {
        return $this->hasMany(VideoCallParticipant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VideoCallEvent::class);
    }

    public function qualityMetrics(): HasMany
    {
        return $this->hasMany(VideoCallQualityMetric::class);
    }

    public function e2eeLogs(): HasMany
    {
        return $this->hasMany(VideoCallE2eeLog::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(VideoCallRecording::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEnded($query)
    {
        return $query->where('status', 'ended');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('call_type', $type);
    }

    public function scopeRecorded($query)
    {
        return $query->where('is_recorded', true);
    }

    public function scopeWithE2EE($query)
    {
        return $query->whereNotNull('e2ee_settings');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isVideoCall(): bool
    {
        return $this->call_type === 'video';
    }

    public function isAudioCall(): bool
    {
        return $this->call_type === 'audio';
    }

    public function hasE2EE(): bool
    {
        return ! empty($this->e2ee_settings);
    }

    public function isRecorded(): bool
    {
        return $this->is_recorded;
    }

    public function getDurationInMinutes(): ?float
    {
        return $this->duration_seconds ? round($this->duration_seconds / 60, 2) : null;
    }

    public function getParticipantCount(): int
    {
        return is_array($this->participants) ? count($this->participants) : 0;
    }

    public function getActiveParticipants(): array
    {
        return $this->callParticipants()
            ->where('status', 'joined')
            ->with('user')
            ->get()
            ->toArray();
    }

    public function addParticipant(User $user, string $participantIdentity): VideoCallParticipant
    {
        return $this->callParticipants()->create([
            'user_id' => $user->id,
            'participant_identity' => $participantIdentity,
            'status' => 'invited',
            'invited_at' => now(),
        ]);
    }

    public function updateStatus(string $status, array $metadata = []): void
    {
        $this->update([
            'status' => $status,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);

        // Record status change event
        $this->events()->create([
            'event_type' => 'status_changed',
            'event_data' => [
                'old_status' => $this->getOriginal('status'),
                'new_status' => $status,
                'metadata' => $metadata,
            ],
            'event_timestamp' => now(),
        ]);
    }

    public function startCall(): void
    {
        $this->update([
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    public function endCall(?string $reason = null): void
    {
        $endedAt = now();
        $duration = $this->started_at ? $this->started_at->diffInSeconds($endedAt) : 0;

        $this->update([
            'status' => 'ended',
            'ended_at' => $endedAt,
            'duration_seconds' => $duration,
            'failure_reason' => $reason,
        ]);

        // End all active participants
        $this->callParticipants()
            ->where('status', 'joined')
            ->update([
                'status' => 'left',
                'left_at' => $endedAt,
                'duration_seconds' => $duration,
            ]);
    }

    public function getE2EESettings(): ?array
    {
        return $this->e2ee_settings;
    }

    public function updateE2EESettings(array $settings): void
    {
        $this->update([
            'e2ee_settings' => array_merge($this->e2ee_settings ?? [], $settings),
        ]);
    }

    public function getQualitySettings(): ?array
    {
        return $this->quality_settings ? json_decode($this->quality_settings, true) : null;
    }

    public function getAverageQualityScore(): ?float
    {
        $avgScore = $this->qualityMetrics()
            ->whereNotNull('quality_score')
            ->avg('quality_score');

        return $avgScore ? round($avgScore, 2) : null;
    }

    public function getCallSummary(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->call_type,
            'status' => $this->status,
            'duration_minutes' => $this->getDurationInMinutes(),
            'participant_count' => $this->getParticipantCount(),
            'has_e2ee' => $this->hasE2EE(),
            'is_recorded' => $this->isRecorded(),
            'average_quality' => $this->getAverageQualityScore(),
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
        ];
    }
}
