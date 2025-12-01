<?php

namespace App\Models\Chat;

use App\Models\Bot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotConversation extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $table = 'bot_conversations';

    protected $fillable = [
        'bot_id',
        'conversation_id',
        'status',
        'permissions',
        'context',
        'last_message_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'context' => 'array',
        'last_message_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BotMessage::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isRemoved(): bool
    {
        return $this->status === 'removed';
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    public function getPermissions(): array
    {
        return $this->permissions ?? [];
    }

    public function getContext(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->context ?? [];
        }
        
        return data_get($this->context, $key, $default);
    }

    public function setContext(string $key, $value): void
    {
        $context = $this->context ?? [];
        data_set($context, $key, $value);
        $this->context = $context;
        $this->save();
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    public function remove(): void
    {
        $this->update(['status' => 'removed']);
    }

    public function updateLastMessageTime(): void
    {
        $this->update(['last_message_at' => now()]);
    }
}