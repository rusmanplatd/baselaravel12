<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReadReceipt extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'chat_message_read_receipts';

    protected $fillable = [
        'message_id',
        'user_id',
        'read_at',
        'is_private',
        'metadata',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'metadata' => 'array',
        'is_private' => 'boolean',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForMessage($query, string $messageId)
    {
        return $query->where('message_id', $messageId);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getDisplayUser(): ?User
    {
        return $this->is_private ? null : $this->user;
    }

    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    public function canBeViewedBy(string $userId): bool
    {
        // User can always see their own read receipt
        if ($this->user_id === $userId) {
            return true;
        }

        // If private, only the user can see it
        if ($this->is_private) {
            return false;
        }

        // Check if user has permission to view read receipts in this conversation
        return $this->message->conversation
            ->participants()
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->exists();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\Chat\MessageReadReceiptFactory::new();
    }
}
