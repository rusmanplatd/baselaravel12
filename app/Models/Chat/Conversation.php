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

class Conversation extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $table = 'chat_conversations';

    protected $fillable = [
        'name',
        'type',
        'description',
        'avatar_url',
        'settings',
        'created_by_user_id',
        'created_by_device_id',
        'organization_id',
        'is_active',
        'last_activity_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => 'direct',
        'is_active' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'description', 'is_active', 'settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Conversation {$eventName}")
            ->useLogName('chat');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'conversation_id');
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(Participant::class, 'conversation_id')->active();
    }

    public function encryptionKeys(): HasMany
    {
        return $this->hasMany(EncryptionKey::class, 'conversation_id');
    }

    public function videoCalls(): HasMany
    {
        return $this->hasMany(VideoCall::class, 'conversation_id');
    }

    public function latestMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId)->active();
        });
    }

    // Helper methods
    public function isDirectMessage(): bool
    {
        return $this->type === 'direct';
    }

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function isChannel(): bool
    {
        return $this->type === 'channel';
    }

    public function isEncrypted(): bool
    {
        return ! empty($this->settings['encryption_algorithm'] ?? null);
    }

    public function getParticipantCount(): int
    {
        return $this->activeParticipants()->count();
    }

    public function hasUser(string $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->active()->exists();
    }

    public function addParticipant(string $userId, array $options = []): Participant
    {
        return $this->participants()->create([
            'user_id' => $userId,
            'role' => $options['role'] ?? 'member',
            'joined_at' => now(),
            'permissions' => $options['permissions'] ?? null,
        ]);
    }

    public function removeParticipant(string $userId): bool
    {
        $participant = $this->participants()->where('user_id', $userId)->first();
        if ($participant) {
            $participant->update(['left_at' => now()]);

            return true;
        }

        return false;
    }
}
