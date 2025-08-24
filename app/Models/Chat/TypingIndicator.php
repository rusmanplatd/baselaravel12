<?php

namespace App\Models\Chat;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TypingIndicator extends Model
{
    use HasUlids;

    protected $table = 'chat_typing_indicators';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'is_typing',
        'last_typed_at',
        'expires_at',
    ];

    protected $casts = [
        'is_typing' => 'boolean',
        'last_typed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeTyping($query)
    {
        return $query->where('is_typing', true)
            ->where('expires_at', '>', Carbon::now());
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    public static function setTyping(string $conversationId, string $userId, int $timeoutSeconds = 10): self
    {
        return static::updateOrCreate(
            ['conversation_id' => $conversationId, 'user_id' => $userId],
            [
                'is_typing' => true,
                'last_typed_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addSeconds($timeoutSeconds),
            ]
        );
    }

    public static function stopTyping(string $conversationId, string $userId): bool
    {
        return static::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['is_typing' => false, 'expires_at' => Carbon::now()]);
    }
}
