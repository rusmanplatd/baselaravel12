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

class ChannelSubscription extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'channel_id',
        'user_id',
        'status',
        'has_notifications',
        'is_muted',
        'subscribed_at',
        'unsubscribed_at',
        'last_viewed_at',
        'last_viewed_message_id',
    ];

    protected $casts = [
        'has_notifications' => 'boolean',
        'is_muted' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'subscribed',
        'has_notifications' => true,
        'is_muted' => false,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'has_notifications', 'is_muted'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Channel subscription {$eventName}")
            ->useLogName('channel');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lastViewedMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_viewed_message_id');
    }

    // Scopes
    public function scopeSubscribed($query)
    {
        return $query->where('status', 'subscribed');
    }

    public function scopeUnsubscribed($query)
    {
        return $query->where('status', 'unsubscribed');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    public function scopeWithNotifications($query)
    {
        return $query->where('has_notifications', true);
    }

    public function scopeNotMuted($query)
    {
        return $query->where('is_muted', false);
    }

    // Helper methods
    public function isSubscribed(): bool
    {
        return $this->status === 'subscribed';
    }

    public function isUnsubscribed(): bool
    {
        return $this->status === 'unsubscribed';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function isMuted(): bool
    {
        return $this->is_muted;
    }

    public function hasNotifications(): bool
    {
        return $this->has_notifications && $this->isSubscribed() && !$this->isMuted();
    }

    public function subscribe(): void
    {
        $this->update([
            'status' => 'subscribed',
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);
    }

    public function unsubscribe(): void
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);
    }

    public function block(): void
    {
        $this->update([
            'status' => 'blocked',
            'unsubscribed_at' => now(),
        ]);
    }

    public function mute(): void
    {
        $this->update(['is_muted' => true]);
    }

    public function unmute(): void
    {
        $this->update(['is_muted' => false]);
    }

    public function toggleNotifications(): void
    {
        $this->update(['has_notifications' => !$this->has_notifications]);
    }

    public function updateLastViewed(?string $messageId = null): void
    {
        $this->update([
            'last_viewed_message_id' => $messageId,
            'last_viewed_at' => now(),
        ]);
    }

    public function getUnreadCount(): int
    {
        if (!$this->isSubscribed()) {
            return 0;
        }

        if (!$this->last_viewed_message_id) {
            return $this->channel->messages()->count();
        }

        $lastViewedMessage = Message::find($this->last_viewed_message_id);
        if (!$lastViewedMessage) {
            return 0;
        }

        return $this->channel->messages()
            ->where('created_at', '>', $lastViewedMessage->created_at)
            ->count();
    }
}