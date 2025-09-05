<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'metadata',
        'is_edited',
        'edited_at',
        'status',
        'file_path',
        'file_name',
        'file_mime_type',
        'file_size',
        'file_hash',
        'file_metadata',
        'file_iv',
        'file_tag',
        'voice_duration_seconds',
        'voice_transcript',
        'voice_waveform_data',
        'encrypted_voice_transcript',
        'encrypted_voice_waveform_data',
        'voice_transcript_hash',
        'voice_waveform_hash',
        'scheduled_at',
        'message_priority',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_metadata' => 'array',
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'file_size' => 'integer',
        'voice_duration_seconds' => 'integer',
    ];

    protected $attributes = [
        'type' => 'text',
        'status' => 'sent',
        'message_priority' => 'normal',
        'is_edited' => false,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'status', 'is_edited', 'message_priority'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Message {$eventName}")
            ->useLogName('chat');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    public function readReceipts(): HasMany
    {
        return $this->hasMany(MessageReadReceipt::class, 'message_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(MessageFile::class, 'message_id');
    }

    public function poll(): HasOne
    {
        return $this->hasOne(\App\Models\Chat\Poll::class, 'message_id');
    }

    public function survey(): HasOne
    {
        return $this->hasOne(\App\Models\Chat\Survey::class, 'message_id');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeInConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeFromUser($query, $userId)
    {
        return $query->where('sender_id', $userId);
    }

    public function scopeScheduled($query)
    {
        return $query->whereNotNull('scheduled_at');
    }

    public function scopePending($query)
    {
        return $query->where('scheduled_at', '>', now());
    }

    public function scopeReady($query)
    {
        return $query->where('scheduled_at', '<=', now());
    }

    public function scopeEdited($query)
    {
        return $query->where('is_edited', true);
    }

    // Helper methods
    public function isText(): bool
    {
        return $this->type === 'text';
    }

    public function isFile(): bool
    {
        return in_array($this->type, ['file', 'image', 'video', 'audio', 'voice']);
    }

    public function isVoice(): bool
    {
        return $this->type === 'voice';
    }

    public function isImage(): bool
    {
        return $this->type === 'image' ||
               ($this->type === 'file' && str_starts_with($this->file_mime_type ?? '', 'image/'));
    }

    public function isVideo(): bool
    {
        return $this->type === 'video' ||
               ($this->type === 'file' && str_starts_with($this->file_mime_type ?? '', 'video/'));
    }

    public function isAudio(): bool
    {
        return $this->type === 'audio' ||
               ($this->type === 'file' && str_starts_with($this->file_mime_type ?? '', 'audio/'));
    }

    public function isPoll(): bool
    {
        return $this->type === 'poll';
    }

    public function isSurvey(): bool
    {
        return $this->type === 'survey';
    }

    public function hasFile(): bool
    {
        return ! empty($this->file_path);
    }

    public function isScheduled(): bool
    {
        return $this->scheduled_at && $this->scheduled_at->isFuture();
    }

    public function isReply(): bool
    {
        return ! empty($this->reply_to_id);
    }

    public function isEdited(): bool
    {
        return $this->is_edited;
    }

    public function getFileSizeFormatted(): ?string
    {
        if (! $this->file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2).' '.$units[$unitIndex];
    }

    public function getVoiceDurationFormatted(): ?string
    {
        if (! $this->voice_duration_seconds) {
            return null;
        }

        $minutes = floor($this->voice_duration_seconds / 60);
        $seconds = $this->voice_duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function markAsRead(string $userId): void
    {
        $this->readReceipts()->updateOrCreate(
            ['user_id' => $userId],
            ['read_at' => now()]
        );
    }

    public function addReaction(string $userId, string $emoji): MessageReaction
    {
        return $this->reactions()->updateOrCreate(
            ['user_id' => $userId, 'emoji' => $emoji],
            ['created_at' => now()]
        );
    }

    public function removeReaction(string $userId, string $emoji): bool
    {
        return $this->reactions()
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->delete() > 0;
    }
}
