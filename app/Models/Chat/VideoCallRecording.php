<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoCallRecording extends Model
{
    use HasUlids;

    protected $fillable = [
        'video_call_id',
        'recording_id',
        'storage_type',
        'file_path',
        'file_format',
        'file_size',
        'duration_seconds',
        'recording_metadata',
        'is_encrypted',
        'encryption_key_id',
        'recording_started_at',
        'recording_ended_at',
        'processing_status',
        'processing_error',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration_seconds' => 'integer',
        'recording_metadata' => 'array',
        'is_encrypted' => 'boolean',
        'recording_started_at' => 'datetime',
        'recording_ended_at' => 'datetime',
    ];

    // Relationships
    public function videoCall(): BelongsTo
    {
        return $this->belongsTo(VideoCall::class);
    }

    // Scopes
    public function scopeProcessing($query)
    {
        return $query->where('processing_status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('processing_status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('processing_status', 'failed');
    }

    public function scopeEncrypted($query)
    {
        return $query->where('is_encrypted', true);
    }

    public function scopeByFormat($query, string $format)
    {
        return $query->where('file_format', $format);
    }

    // Helper methods
    public function isProcessing(): bool
    {
        return $this->processing_status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->processing_status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->processing_status === 'failed';
    }

    public function isEncrypted(): bool
    {
        return $this->is_encrypted;
    }

    public function getDurationInMinutes(): ?float
    {
        return $this->duration_seconds ? round($this->duration_seconds / 60, 2) : null;
    }

    public function getFileSizeInMB(): ?float
    {
        return $this->file_size ? round($this->file_size / (1024 * 1024), 2) : null;
    }

    public function getRecordingDuration(): ?int
    {
        if (! $this->recording_started_at || ! $this->recording_ended_at) {
            return null;
        }

        return $this->recording_started_at->diffInSeconds($this->recording_ended_at);
    }

    public function getDownloadUrl(): ?string
    {
        if ($this->processing_status !== 'completed') {
            return null;
        }

        // Generate signed URL for download
        // This would typically integrate with your storage service
        return route('api.chat.recordings.download', $this->id);
    }

    public function getMetadata(?string $key = null)
    {
        if ($key === null) {
            return $this->recording_metadata;
        }

        return $this->recording_metadata[$key] ?? null;
    }

    public function markAsCompleted(array $metadata = []): void
    {
        $this->update([
            'processing_status' => 'completed',
            'recording_ended_at' => now(),
            'recording_metadata' => array_merge($this->recording_metadata ?? [], $metadata),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'processing_status' => 'failed',
            'processing_error' => $error,
        ]);
    }

    public function getRecordingSummary(): array
    {
        return [
            'id' => $this->id,
            'recording_id' => $this->recording_id,
            'format' => $this->file_format,
            'duration_minutes' => $this->getDurationInMinutes(),
            'file_size_mb' => $this->getFileSizeInMB(),
            'status' => $this->processing_status,
            'encrypted' => $this->is_encrypted,
            'download_url' => $this->getDownloadUrl(),
            'started_at' => $this->recording_started_at?->toISOString(),
            'ended_at' => $this->recording_ended_at?->toISOString(),
        ];
    }
}
