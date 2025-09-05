<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoCallParticipant extends Model
{
    use HasUlids;

    protected $fillable = [
        'video_call_id',
        'user_id',
        'participant_identity',
        'status',
        'invited_at',
        'joined_at',
        'left_at',
        'duration_seconds',
        'connection_quality',
        'media_tracks',
        'device_info',
        'rejection_reason',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'duration_seconds' => 'integer',
        'connection_quality' => 'array',
        'media_tracks' => 'array',
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

    public function qualityMetrics(): HasMany
    {
        return $this->hasMany(VideoCallQualityMetric::class, 'participant_id');
    }

    public function e2eeLogs(): HasMany
    {
        return $this->hasMany(VideoCallE2eeLog::class, 'participant_id');
    }

    // Scopes
    public function scopeJoined($query)
    {
        return $query->where('status', 'joined');
    }

    public function scopeLeft($query)
    {
        return $query->where('status', 'left');
    }

    public function scopeInvited($query)
    {
        return $query->where('status', 'invited');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Helper methods
    public function isJoined(): bool
    {
        return $this->status === 'joined';
    }

    public function hasLeft(): bool
    {
        return $this->status === 'left';
    }

    public function wasInvited(): bool
    {
        return $this->status === 'invited';
    }

    public function wasRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function wasMissed(): bool
    {
        return $this->status === 'missed';
    }

    public function joinCall(): void
    {
        $this->update([
            'status' => 'joined',
            'joined_at' => now(),
        ]);
    }

    public function leaveCall(?string $reason = null): void
    {
        $leftAt = now();
        $duration = $this->joined_at ? $this->joined_at->diffInSeconds($leftAt) : 0;

        $this->update([
            'status' => 'left',
            'left_at' => $leftAt,
            'duration_seconds' => $duration,
            'rejection_reason' => $reason,
        ]);
    }

    public function rejectCall(?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    public function markAsMissed(): void
    {
        $this->update([
            'status' => 'missed',
        ]);
    }

    public function getDurationInMinutes(): ?float
    {
        return $this->duration_seconds ? round($this->duration_seconds / 60, 2) : null;
    }

    public function updateConnectionQuality(array $quality): void
    {
        $this->update([
            'connection_quality' => array_merge($this->connection_quality ?? [], $quality),
        ]);
    }

    public function updateMediaTracks(array $tracks): void
    {
        $this->update([
            'media_tracks' => $tracks,
        ]);
    }

    public function getLatestQualityMetric(): ?VideoCallQualityMetric
    {
        return $this->qualityMetrics()
            ->orderBy('measured_at', 'desc')
            ->first();
    }

    public function getAverageQualityScore(): ?float
    {
        $avgScore = $this->qualityMetrics()
            ->whereNotNull('quality_score')
            ->avg('quality_score');

        return $avgScore ? round($avgScore, 2) : null;
    }

    public function hasVideo(): bool
    {
        return isset($this->media_tracks['video']) && $this->media_tracks['video'];
    }

    public function hasAudio(): bool
    {
        return isset($this->media_tracks['audio']) && $this->media_tracks['audio'];
    }

    public function hasScreenShare(): bool
    {
        return isset($this->media_tracks['screen']) && $this->media_tracks['screen'];
    }

    public function getDeviceInfo(): ?array
    {
        return $this->device_info ? json_decode($this->device_info, true) : null;
    }

    public function updateDeviceInfo(array $deviceInfo): void
    {
        $this->update([
            'device_info' => json_encode($deviceInfo),
        ]);
    }

    public function getParticipantSummary(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'participant_identity' => $this->participant_identity,
            'status' => $this->status,
            'duration_minutes' => $this->getDurationInMinutes(),
            'has_video' => $this->hasVideo(),
            'has_audio' => $this->hasAudio(),
            'has_screen_share' => $this->hasScreenShare(),
            'average_quality' => $this->getAverageQualityScore(),
            'joined_at' => $this->joined_at?->toISOString(),
            'left_at' => $this->left_at?->toISOString(),
        ];
    }
}
