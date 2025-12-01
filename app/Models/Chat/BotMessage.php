<?php

namespace App\Models\Chat;

use App\Models\Bot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotMessage extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $table = 'bot_messages';

    protected $fillable = [
        'bot_id',
        'conversation_id',
        'bot_conversation_id',
        'message_id',
        'direction',
        'content',
        'encrypted_content',
        'encryption_version',
        'content_type',
        'metadata',
        'processed_at',
        'response_sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'response_sent_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function botConversation(): BelongsTo
    {
        return $this->belongsTo(BotConversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isIncoming(): bool
    {
        return $this->direction === 'incoming';
    }

    public function isOutgoing(): bool
    {
        return $this->direction === 'outgoing';
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function isResponseSent(): bool
    {
        return $this->response_sent_at !== null;
    }

    public function isEncrypted(): bool
    {
        return !empty($this->encrypted_content);
    }

    public function getContent(): string
    {
        return $this->content ?? '';
    }

    public function getEncryptedContent(): ?string
    {
        return $this->encrypted_content;
    }

    public function getContentType(): string
    {
        return $this->content_type ?? 'text';
    }

    public function getMetadata(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->metadata ?? [];
        }
        
        return data_get($this->metadata, $key, $default);
    }

    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;
        $this->save();
    }

    public function markAsProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }

    public function markResponseSent(): void
    {
        $this->update(['response_sent_at' => now()]);
    }

    public function getEncryptionVersion(): int
    {
        return $this->encryption_version ?? 1;
    }

    public function isQuantumEncrypted(): bool
    {
        return $this->getEncryptionVersion() >= 3;
    }
}