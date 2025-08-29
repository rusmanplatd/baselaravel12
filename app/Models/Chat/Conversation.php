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
        'metadata',
        'status',
        'last_message_at',
        'created_by',
        'encryption_algorithm',
        'key_strength',
        'encryption_info',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'key_strength' => 'integer',
        'encryption_info' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()->whereNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function encryptionKeys(): HasMany
    {
        return $this->hasMany(EncryptionKey::class);
    }

    public function isDirectConversation(): bool
    {
        return $this->type === 'direct';
    }

    public function isGroupConversation(): bool
    {
        return $this->type === 'group';
    }

    public function isEncrypted(): bool
    {
        return true; // All conversations are always encrypted in E2EE
    }

    public function updateEncryptionSettings(string $algorithm = 'AES-256-GCM', int $keyStrength = 256, array $info = []): void
    {
        $this->update([
            'encryption_algorithm' => $algorithm,
            'key_strength' => $keyStrength,
            'encryption_info' => $info,
        ]);
    }

    public function getEncryptionInfo(): array
    {
        return [
            'algorithm' => $this->encryption_algorithm ?? 'AES-256-GCM',
            'key_strength' => $this->key_strength ?? 256,
            'additional_info' => $this->encryption_info ?? [],
        ];
    }

    public function getEncryptionInfoAttribute(): array
    {
        return $this->getEncryptionInfo();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId)->whereNull('left_at');
        });
    }

    public function scopeEncrypted($query)
    {
        return $query; // All conversations are encrypted in E2EE
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'description', 'status', 'encryption_algorithm', 'key_strength', 'encryption_info'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Chat conversation {$eventName}")
            ->useLogName('chat');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\Chat\ConversationFactory::new();
    }
}
