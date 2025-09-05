<?php

namespace App\Models\Chat;

use App\Models\Bot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotEncryptionKey extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $table = 'bot_encryption_keys';

    protected $fillable = [
        'bot_id',
        'conversation_id',
        'key_type',
        'algorithm',
        'public_key',
        'encrypted_private_key',
        'key_pair_id',
        'version',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'version' => 'integer',
    ];

    protected $hidden = [
        'encrypted_private_key',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRSA(): bool
    {
        return str_starts_with($this->algorithm, 'RSA');
    }

    public function isQuantum(): bool
    {
        return str_contains($this->algorithm, 'ML-KEM');
    }

    public function isHybrid(): bool
    {
        return str_contains($this->algorithm, 'HYBRID');
    }

    public function getKeyType(): string
    {
        return $this->key_type;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function getPublicKey(): string
    {
        return $this->public_key;
    }

    public function getEncryptedPrivateKey(): string
    {
        return $this->encrypted_private_key;
    }

    public function getVersion(): int
    {
        return $this->version ?? 1;
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function extend(int $hours = 24): void
    {
        $this->update([
            'expires_at' => now()->addHours($hours)
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    public function scopeForBot($query, $botId)
    {
        return $query->where('bot_id', $botId);
    }

    public function scopeForConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeByAlgorithm($query, string $algorithm)
    {
        return $query->where('algorithm', $algorithm);
    }
}