<?php

namespace App\Models\Chat;

use App\Models\User;
use App\Services\ChatEncryptionService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Message extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $table = 'chat_messages';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'reply_to_id',
        'type',
        'encrypted_content',
        'content_hash',
        'content_hmac',
        'voice_duration_seconds',
        'voice_transcript',
        'voice_waveform_data',
        'encrypted_voice_transcript',
        'encrypted_voice_waveform_data',
        'voice_transcript_hash',
        'voice_waveform_hash',
        'scheduled_at',
        'message_priority',
        'metadata',
        'is_edited',
        'edited_at',
        'status',
        'file_path',
        'file_name',
        'file_mime_type',
        'file_size',
        'file_iv',
        'file_tag',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    // Don't automatically append - only when loaded

    protected $hidden = [
        'encrypted_content',
        'content_hash',
        'content_hmac',
        'encrypted_voice_transcript',
        'encrypted_voice_waveform_data',
        'voice_transcript_hash',
        'voice_waveform_hash',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function getReplyToAttribute()
    {
        return $this->relationLoaded('replyTo') ? $this->getRelation('replyTo') : null;
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function readReceipts(): HasMany
    {
        return $this->hasMany(MessageReadReceipt::class);
    }

    public function decryptContent(string $symmetricKey): string
    {
        $encryptionService = app(ChatEncryptionService::class);

        $encryptedData = json_decode($this->encrypted_content, true);

        $decryptedContent = $encryptionService->decryptMessage(
            $encryptedData['data'],
            $encryptedData['iv'],
            $symmetricKey,
            $encryptedData['hmac'] ?? null,
            $encryptedData['auth_data'] ?? null
        );

        if (! $encryptionService->verifyMessageHash($decryptedContent, $this->content_hash)) {
            throw new \RuntimeException('Message integrity check failed');
        }

        return $decryptedContent;
    }

    public static function createEncrypted(
        string $conversationId,
        string $senderId,
        string $content,
        string $symmetricKey,
        array $additionalData = []
    ): self {
        $encryptionService = app(ChatEncryptionService::class);

        $encrypted = $encryptionService->encryptMessage($content, $symmetricKey);

        // Prepare encrypted content with all necessary fields
        $encryptedContent = [
            'data' => $encrypted['data'],
            'iv' => $encrypted['iv'],
            'hmac' => $encrypted['hmac'],
            'auth_data' => $encrypted['auth_data'],
            'timestamp' => $encrypted['timestamp'],
            'nonce' => $encrypted['nonce'],
        ];

        $messageData = [
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'encrypted_content' => json_encode($encryptedContent),
            'content_hash' => $encrypted['hash'],
            'content_hmac' => $encrypted['hmac'],
        ];

        // Handle encrypted voice data if provided
        if (isset($additionalData['voice_transcript'])) {
            $transcriptEncrypted = $encryptionService->encryptMessage($additionalData['voice_transcript'], $symmetricKey);
            $messageData['encrypted_voice_transcript'] = json_encode([
                'data' => $transcriptEncrypted['data'],
                'iv' => $transcriptEncrypted['iv'],
                'hmac' => $transcriptEncrypted['hmac'],
                'auth_data' => $transcriptEncrypted['auth_data'],
            ]);
            $messageData['voice_transcript_hash'] = $transcriptEncrypted['hash'];
            unset($additionalData['voice_transcript']);
        }

        if (isset($additionalData['voice_waveform_data'])) {
            $waveformEncrypted = $encryptionService->encryptMessage($additionalData['voice_waveform_data'], $symmetricKey);
            $messageData['encrypted_voice_waveform_data'] = json_encode([
                'data' => $waveformEncrypted['data'],
                'iv' => $waveformEncrypted['iv'],
                'hmac' => $waveformEncrypted['hmac'],
                'auth_data' => $waveformEncrypted['auth_data'],
            ]);
            $messageData['voice_waveform_hash'] = $waveformEncrypted['hash'];
            unset($additionalData['voice_waveform_data']);
        }

        return self::create(array_merge($messageData, $additionalData));
    }

    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function scopeScheduled($query)
    {
        return $query->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now());
    }

    public function scopeVoiceMessages($query)
    {
        return $query->where('type', 'voice')
            ->whereNotNull('voice_duration_seconds');
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('message_priority', $priority);
    }

    public function isVoiceMessage(): bool
    {
        return $this->type === 'voice' && ! is_null($this->voice_duration_seconds);
    }

    public function decryptVoiceTranscript(string $symmetricKey): ?string
    {
        if (! $this->encrypted_voice_transcript) {
            return $this->voice_transcript;
        }

        $encryptionService = app(ChatEncryptionService::class);
        $encryptedData = json_decode($this->encrypted_voice_transcript, true);

        if (! $encryptedData) {
            return null;
        }

        try {
            $decryptedContent = $encryptionService->decryptMessage(
                $encryptedData['data'],
                $encryptedData['iv'],
                $symmetricKey,
                $encryptedData['hmac'] ?? null,
                $encryptedData['auth_data'] ?? null
            );

            // Verify hash if available
            if ($this->voice_transcript_hash &&
                ! $encryptionService->verifyMessageHash($decryptedContent, $this->voice_transcript_hash)) {
                throw new \RuntimeException('Voice transcript integrity check failed');
            }

            return $decryptedContent;
        } catch (\Exception $e) {
            Log::error('Failed to decrypt voice transcript', [
                'message_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function decryptVoiceWaveformData(string $symmetricKey): ?string
    {
        if (! $this->encrypted_voice_waveform_data) {
            return $this->voice_waveform_data;
        }

        $encryptionService = app(ChatEncryptionService::class);
        $encryptedData = json_decode($this->encrypted_voice_waveform_data, true);

        if (! $encryptedData) {
            return null;
        }

        try {
            $decryptedContent = $encryptionService->decryptMessage(
                $encryptedData['data'],
                $encryptedData['iv'],
                $symmetricKey,
                $encryptedData['hmac'] ?? null,
                $encryptedData['auth_data'] ?? null
            );

            // Verify hash if available
            if ($this->voice_waveform_hash &&
                ! $encryptionService->verifyMessageHash($decryptedContent, $this->voice_waveform_hash)) {
                throw new \RuntimeException('Voice waveform data integrity check failed');
            }

            return $decryptedContent;
        } catch (\Exception $e) {
            Log::error('Failed to decrypt voice waveform data', [
                'message_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function isScheduled(): bool
    {
        return ! is_null($this->scheduled_at) && $this->scheduled_at->isFuture();
    }

    public function getReactionSummary(): array
    {
        return $this->reactions()
            ->selectRaw('emoji, count(*) as count')
            ->groupBy('emoji')
            ->pluck('count', 'emoji')
            ->toArray();
    }

    public function getReadByUserIds(): array
    {
        return $this->readReceipts()
            ->pluck('user_id')
            ->toArray();
    }

    public function getRootMessage(): Message
    {
        $message = $this;
        while ($message->reply_to_id) {
            $message = $message->replyTo;
        }

        return $message;
    }

    public function getThreadId(): string
    {
        return $this->getRootMessage()->id;
    }

    public function getThreadMessages(): \Illuminate\Database\Eloquent\Collection
    {
        $threadId = $this->getThreadId();

        return self::where(function ($query) use ($threadId) {
            $query->where('id', $threadId)
                ->orWhere('reply_to_id', $threadId)
                ->orWhereHas('replyTo', function ($q) use ($threadId) {
                    $q->where('reply_to_id', $threadId);
                });
        })
            ->with('sender:id,name,email', 'replyTo.sender:id,name,email')
            ->orderBy('created_at')
            ->get();
    }

    public function getThreadRepliesCount(): int
    {
        $threadId = $this->getThreadId();

        return self::where('reply_to_id', $threadId)
            ->orWhere(function ($query) use ($threadId) {
                $query->whereHas('replyTo', function ($q) use ($threadId) {
                    $q->where('reply_to_id', $threadId);
                });
            })
            ->count();
    }

    public function scopeThreadRoots($query)
    {
        return $query->whereNull('reply_to_id');
    }

    public function scopeWithThreadContext($query)
    {
        return $query->with([
            'sender:id,name,email',
            'replyTo.sender:id,name,email',
        ]);
    }

    public function scopeForThread($query, string $threadId)
    {
        return $query->where(function ($q) use ($threadId) {
            $q->where('id', $threadId)
                ->orWhere('reply_to_id', $threadId)
                ->orWhereHas('replyTo', function ($subQ) use ($threadId) {
                    $subQ->where('reply_to_id', $threadId);
                });
        });
    }

    public function isThreadRoot(): bool
    {
        return is_null($this->reply_to_id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('chat')
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\Chat\MessageFactory::new();
    }
}
