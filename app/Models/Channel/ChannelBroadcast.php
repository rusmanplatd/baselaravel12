<?php

namespace App\Models\Channel;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ChannelBroadcast extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'channel_id',
        'created_by_user_id',
        'message_id',
        'title',
        'content',
        'media_attachments',
        'status',
        'scheduled_at',
        'sent_at',
        'recipient_count',
        'delivered_count',
        'read_count',
        'broadcast_settings',
    ];

    protected $casts = [
        'media_attachments' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'recipient_count' => 'integer',
        'delivered_count' => 'integer',
        'read_count' => 'integer',
        'broadcast_settings' => 'array',
    ];

    protected $attributes = [
        'status' => 'draft',
        'recipient_count' => 0,
        'delivered_count' => 0,
        'read_count' => 0,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'scheduled_at', 'recipient_count'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Channel broadcast {$eventName}")
            ->useLogName('channel');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'channel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    // Scopes
    public function scopeDrafts($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopePendingSend($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeByCreator($query, $userId)
    {
        return $query->where('created_by_user_id', $userId);
    }

    // Helper methods
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->isScheduled() && $this->scheduled_at <= now();
    }

    public function canBeSent(): bool
    {
        return $this->isDraft() || $this->isPending();
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft() || $this->isScheduled();
    }

    public function canBeDeleted(): bool
    {
        return !$this->isSent();
    }

    public function schedule(\Carbon\Carbon $scheduledAt): void
    {
        $this->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function updateRecipientCount(int $count): void
    {
        $this->update(['recipient_count' => $count]);
    }

    public function incrementDelivered(): void
    {
        $this->increment('delivered_count');
    }

    public function incrementRead(): void
    {
        $this->increment('read_count');
    }

    public function getDeliveryRate(): float
    {
        if ($this->recipient_count === 0) {
            return 0.0;
        }

        return round(($this->delivered_count / $this->recipient_count) * 100, 2);
    }

    public function getReadRate(): float
    {
        if ($this->delivered_count === 0) {
            return 0.0;
        }

        return round(($this->read_count / $this->delivered_count) * 100, 2);
    }

    public function getEngagementRate(): float
    {
        if ($this->delivered_count === 0) {
            return 0.0;
        }

        return round(($this->read_count / $this->delivered_count) * 100, 2);
    }

    public function isSilent(): bool
    {
        return $this->broadcast_settings['silent'] ?? false;
    }

    public function hasMediaAttachments(): bool
    {
        return !empty($this->media_attachments);
    }

    public function getEstimatedDeliveryTime(): ?\Carbon\Carbon
    {
        if (!$this->isScheduled()) {
            return null;
        }

        return $this->scheduled_at;
    }

    public function getDuration(): ?int
    {
        if (!$this->sent_at) {
            return null;
        }

        return $this->sent_at->diffInSeconds($this->created_at);
    }

    public function duplicate(): self
    {
        $broadcast = $this->replicate([
            'message_id',
            'status',
            'scheduled_at',
            'sent_at',
            'recipient_count',
            'delivered_count',
            'read_count',
        ]);
        
        $broadcast->title = $this->title . ' (Copy)';
        $broadcast->save();

        return $broadcast;
    }
}