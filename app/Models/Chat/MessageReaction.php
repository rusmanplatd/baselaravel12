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
        'reaction_type',
    ];

    protected $attributes = [
        'reaction_type' => 'emoji',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeByEmoji($query, $emoji)
    {
        return $query->where('emoji', $emoji);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForMessage($query, $messageId)
    {
        return $query->where('message_id', $messageId);
    }

    // Helper methods
    public function isEmoji(): bool
    {
        return $this->reaction_type === 'emoji';
    }

    public function isCustom(): bool
    {
        return $this->reaction_type === 'custom';
    }
}
