<?php

namespace App\Models;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ScheduledMessage extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $table = 'scheduled_messages';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'content',
        'content_type',
        'scheduled_for',
        'timezone',
        'status',
        'retry_count',
        'max_retries',
        'error_message',
        'sent_message_id',
        'sent_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function sentMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'sent_message_id');
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isSending(): bool
    {
        return $this->status === 'sending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isReadyToSend(): bool
    {
        return $this->isScheduled() && $this->scheduled_for <= now();
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < $this->max_retries;
    }

    public function canCancel(): bool
    {
        return $this->isScheduled() || $this->isFailed();
    }

    public function getScheduledForInTimezone(): Carbon
    {
        return $this->scheduled_for->setTimezone($this->timezone ?? 'UTC');
    }

    public function getTimeUntilSend(): ?int
    {
        if (!$this->isScheduled()) {
            return null;
        }

        $diff = $this->scheduled_for->diffInSeconds(now(), false);
        return $diff > 0 ? 0 : abs($diff);
    }

    public function markAsSending(): void
    {
        $this->update(['status' => 'sending']);
    }

    public function markAsSent(string $messageId): void
    {
        $this->update([
            'status' => 'sent',
            'sent_message_id' => $messageId,
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function retry(): void
    {
        if (!$this->canRetry()) {
            throw new \Exception('Cannot retry this scheduled message');
        }

        $this->update([
            'status' => 'scheduled',
            'error_message' => null,
        ]);
    }

    public function reschedule(Carbon $newTime): void
    {
        if (!$this->canCancel()) {
            throw new \Exception('Cannot reschedule this message');
        }

        $this->update([
            'scheduled_for' => $newTime,
            'status' => 'scheduled',
            'error_message' => null,
        ]);
    }

    public function getContentType(): string
    {
        return $this->content_type ?? 'text';
    }

    public function getContent(): string
    {
        return $this->content ?? '';
    }

    public function getMetadata(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->metadata ?? [];
        }
        
        return data_get($this->metadata, $key, $default);
    }

    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;
        $this->save();
    }

    public function scopeReadyToSend($query)
    {
        return $query->where('status', 'scheduled')
                    ->where('scheduled_for', '<=', now());
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeForSender($query, string $senderId)
    {
        return $query->where('sender_id', $senderId);
    }

    public function scopeFailedRetryable($query)
    {
        return $query->where('status', 'failed')
                    ->whereRaw('retry_count < max_retries');
    }

    public function scopeOverdue($query, int $minutes = 30)
    {
        return $query->where('status', 'sending')
                    ->where('updated_at', '<=', now()->subMinutes($minutes));
    }
}