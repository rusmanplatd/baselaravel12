<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoCallQualityMetric extends Model
{
    use HasUlids;

    protected $fillable = [
        'video_call_id',
        'participant_id',
        'measured_at',
        'video_metrics',
        'audio_metrics',
        'connection_metrics',
        'quality_score',
    ];

    protected $casts = [
        'measured_at' => 'datetime',
        'video_metrics' => 'array',
        'audio_metrics' => 'array',
        'connection_metrics' => 'array',
        'quality_score' => 'integer',
    ];

    // Relationships
    public function videoCall(): BelongsTo
    {
        return $this->belongsTo(VideoCall::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(VideoCallParticipant::class, 'participant_id');
    }

    // Scopes
    public function scopeGoodQuality($query)
    {
        return $query->where('quality_score', '>=', 4);
    }

    public function scopePoorQuality($query)
    {
        return $query->where('quality_score', '<=', 2);
    }

    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('measured_at', '>=', now()->subMinutes($minutes));
    }

    // Helper methods
    public function hasGoodQuality(): bool
    {
        return $this->quality_score >= 4;
    }

    public function hasPoorQuality(): bool
    {
        return $this->quality_score <= 2;
    }

    public function getVideoResolution(): ?string
    {
        $width = $this->video_metrics['width'] ?? null;
        $height = $this->video_metrics['height'] ?? null;

        return ($width && $height) ? "{$width}x{$height}" : null;
    }

    public function getVideoBitrate(): ?int
    {
        return $this->video_metrics['bitrate'] ?? null;
    }

    public function getVideoFramerate(): ?int
    {
        return $this->video_metrics['framerate'] ?? null;
    }

    public function getVideoPacketLoss(): ?float
    {
        return $this->video_metrics['packet_loss'] ?? null;
    }

    public function getAudioBitrate(): ?int
    {
        return $this->audio_metrics['bitrate'] ?? null;
    }

    public function getAudioPacketLoss(): ?float
    {
        return $this->audio_metrics['packet_loss'] ?? null;
    }

    public function getAudioJitter(): ?float
    {
        return $this->audio_metrics['jitter'] ?? null;
    }

    public function getRoundTripTime(): ?int
    {
        return $this->connection_metrics['rtt'] ?? null;
    }

    public function getBandwidth(): ?int
    {
        return $this->connection_metrics['bandwidth'] ?? null;
    }

    public function getConnectionType(): ?string
    {
        return $this->connection_metrics['connection_type'] ?? null;
    }

    public function calculateQualityScore(): int
    {
        $score = 5; // Start with perfect score

        // Penalize for high packet loss
        $videoPacketLoss = $this->getVideoPacketLoss() ?? 0;
        $audioPacketLoss = $this->getAudioPacketLoss() ?? 0;

        if ($videoPacketLoss > 5 || $audioPacketLoss > 5) {
            $score -= 2;
        } elseif ($videoPacketLoss > 2 || $audioPacketLoss > 2) {
            $score -= 1;
        }

        // Penalize for high RTT
        $rtt = $this->getRoundTripTime() ?? 0;
        if ($rtt > 300) {
            $score -= 2;
        } elseif ($rtt > 150) {
            $score -= 1;
        }

        // Penalize for low bandwidth
        $bandwidth = $this->getBandwidth() ?? 0;
        if ($bandwidth < 500000) { // Less than 500 kbps
            $score -= 1;
        }

        // Penalize for high jitter
        $jitter = $this->getAudioJitter() ?? 0;
        if ($jitter > 50) {
            $score -= 1;
        }

        return max(1, min(5, $score));
    }

    public function updateQualityScore(): void
    {
        $this->update([
            'quality_score' => $this->calculateQualityScore(),
        ]);
    }

    public function getMetricsSummary(): array
    {
        return [
            'quality_score' => $this->quality_score,
            'video' => [
                'resolution' => $this->getVideoResolution(),
                'bitrate' => $this->getVideoBitrate(),
                'framerate' => $this->getVideoFramerate(),
                'packet_loss' => $this->getVideoPacketLoss(),
            ],
            'audio' => [
                'bitrate' => $this->getAudioBitrate(),
                'packet_loss' => $this->getAudioPacketLoss(),
                'jitter' => $this->getAudioJitter(),
            ],
            'connection' => [
                'rtt' => $this->getRoundTripTime(),
                'bandwidth' => $this->getBandwidth(),
                'type' => $this->getConnectionType(),
            ],
            'measured_at' => $this->measured_at->toISOString(),
        ];
    }
}
