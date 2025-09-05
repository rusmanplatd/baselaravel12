<?php

namespace App\Models;

use App\Models\Chat\Message;
use App\Models\Chat\MessageAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceTranscription extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $table = 'voice_transcriptions';

    protected $fillable = [
        'message_id',
        'attachment_id',
        'transcript',
        'language',
        'confidence',
        'duration',
        'word_count',
        'segments',
        'status',
        'provider',
        'error_message',
        'retry_count',
        'processed_at',
    ];

    protected $casts = [
        'segments' => 'array',
        'confidence' => 'float',
        'duration' => 'float',
        'word_count' => 'integer',
        'retry_count' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(MessageAttachment::class, 'attachment_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function hasTranscript(): bool
    {
        return $this->isCompleted() && !empty($this->transcript);
    }

    public function getTranscript(): ?string
    {
        return $this->transcript;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function getConfidencePercentage(): ?int
    {
        return $this->confidence ? (int) round($this->confidence) : null;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function getDurationFormatted(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getWordCount(): int
    {
        return $this->word_count ?? 0;
    }

    public function getSegments(): array
    {
        return $this->segments ?? [];
    }

    public function hasSegments(): bool
    {
        return !empty($this->segments);
    }

    public function getProvider(): string
    {
        return $this->provider ?? 'unknown';
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < 3;
    }

    public function getRetryCount(): int
    {
        return $this->retry_count ?? 0;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error_message;
    }

    public function getProcessedAt(): ?\Carbon\Carbon
    {
        return $this->processed_at;
    }

    public function getProcessingTime(): ?int
    {
        if (!$this->processed_at) {
            return null;
        }

        return $this->created_at->diffInSeconds($this->processed_at);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeMinConfidence($query, float $minConfidence)
    {
        return $query->where('confidence', '>=', $minConfidence);
    }
}