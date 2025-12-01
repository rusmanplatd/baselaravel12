<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Thread extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $table = 'chat_threads';

    protected $fillable = [
        'conversation_id',
        'parent_message_id',
        'creator_id',
        'title',
        'encrypted_title',
        'title_hash',
        'is_active',
        'participant_count',
        'message_count',
        'last_message_at',
        'last_message_id',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'participant_count' => 'integer',
        'message_count' => 'integer',
        'last_message_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
        'participant_count' => 0,
        'message_count' => 0,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'is_active', 'participant_count', 'message_count'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Thread {$eventName}")
            ->useLogName('chat');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_message_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id', 'parent_message_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ThreadParticipant::class, 'thread_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeWithParentMessage($query, string $messageId)
    {
        return $query->where('parent_message_id', $messageId);
    }

    public function scopeByCreator($query, string $creatorId)
    {
        return $query->where('creator_id', $creatorId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('last_message_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasMessages(): bool
    {
        return $this->message_count > 0;
    }

    public function addMessage(Message $message): void
    {
        $this->increment('message_count');
        $this->update([
            'last_message_at' => $message->created_at,
            'last_message_id' => $message->id,
        ]);

        // Add user as participant if not already
        $this->addParticipant($message->sender_id);
    }

    public function addParticipant(string $userId): void
    {
        $participant = $this->participants()->firstOrCreate([
            'user_id' => $userId,
        ], [
            'joined_at' => now(),
        ]);

        if ($participant->wasRecentlyCreated) {
            $this->increment('participant_count');
        }
    }

    public function removeParticipant(string $userId): bool
    {
        $participant = $this->participants()->where('user_id', $userId)->first();
        
        if ($participant) {
            $participant->update(['left_at' => now()]);
            $this->decrement('participant_count');
            return true;
        }

        return false;
    }

    public function hasParticipant(string $userId): bool
    {
        return $this->participants()
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->exists();
    }

    public function getActiveParticipants()
    {
        return $this->participants()
            ->whereNull('left_at')
            ->with('user')
            ->get();
    }

    public function archive(): void
    {
        $this->update(['is_active' => false]);
    }

    public function restore(): void
    {
        $this->update(['is_active' => true]);
    }

    public function getTitle(): string
    {
        if ($this->title) {
            return $this->title;
        }

        // Auto-generate title from parent message
        if ($this->parentMessage) {
            $content = $this->parentMessage->decrypted_content ?? 'Thread';
            return 'Reply to: ' . Str::limit($content, 50);
        }

        return 'Thread #' . $this->id;
    }

    public function getLastActivity(): array
    {
        return [
            'last_message_at' => $this->last_message_at,
            'last_message_id' => $this->last_message_id,
            'message_count' => $this->message_count,
            'participant_count' => $this->participant_count,
        ];
    }

    public function getMentionableUsers()
    {
        // Get users from parent conversation
        return $this->conversation
            ->participants()
            ->active()
            ->with('user')
            ->get()
            ->pluck('user');
    }
}