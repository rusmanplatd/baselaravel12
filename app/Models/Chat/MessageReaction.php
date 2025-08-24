<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReaction extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'chat_message_reactions';

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
        'is_anonymous',
        'metadata',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected $casts = [
        'metadata' => 'array',
        'is_anonymous' => 'boolean',
    ];

    public function getDisplayUser(): ?User
    {
        return $this->is_anonymous ? null : $this->user;
    }

    public function scopePublic($query)
    {
        return $query->where('is_anonymous', false);
    }

    public function scopeAnonymous($query)
    {
        return $query->where('is_anonymous', true);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\Chat\MessageReactionFactory::new();
    }

    public function scopeForMessage($query, string $messageId)
    {
        return $query->where('message_id', $messageId);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByEmoji($query, string $emoji)
    {
        return $query->where('emoji', $emoji);
    }
}
