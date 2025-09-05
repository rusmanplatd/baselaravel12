<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadParticipant extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'chat_thread_participants';

    protected $fillable = [
        'thread_id',
        'user_id',
        'joined_at',
        'left_at',
        'last_read_message_id',
        'notification_settings',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'notification_settings' => 'array',
    ];

    protected $attributes = [
        'notification_settings' => '{"mentions": true, "replies": true, "all_messages": false}',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    public function scopeInThread($query, string $threadId)
    {
        return $query->where('thread_id', $threadId);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Helper methods
    public function isActive(): bool
    {
        return is_null($this->left_at);
    }

    public function leave(): void
    {
        $this->update(['left_at' => now()]);
    }

    public function rejoin(): void
    {
        $this->update(['left_at' => null]);
    }

    public function markAsRead(string $messageId): void
    {
        $this->update(['last_read_message_id' => $messageId]);
    }

    public function getUnreadCount(): int
    {
        if (!$this->last_read_message_id) {
            return $this->thread->message_count;
        }

        return $this->thread->messages()
            ->where('id', '>', $this->last_read_message_id)
            ->count();
    }

    public function shouldNotifyFor(string $messageType): bool
    {
        $settings = $this->notification_settings;
        
        switch ($messageType) {
            case 'mention':
                return $settings['mentions'] ?? true;
            case 'reply':
                return $settings['replies'] ?? true;
            case 'message':
                return $settings['all_messages'] ?? false;
            default:
                return false;
        }
    }

    public function updateNotificationSettings(array $settings): void
    {
        $this->update([
            'notification_settings' => array_merge(
                $this->notification_settings,
                $settings
            )
        ]);
    }
}