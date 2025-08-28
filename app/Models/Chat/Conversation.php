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
        'is_encrypted',
        'encryption_algorithm',
        'key_strength',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'is_encrypted' => 'boolean',
        'key_strength' => 'integer',
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
        return $this->is_encrypted === true;
    }

    public function enableEncryption(string $algorithm = 'RSA-4096-OAEP', int $keyStrength = 4096): void
    {
        $this->update([
            'is_encrypted' => true,
            'encryption_algorithm' => $algorithm,
            'key_strength' => $keyStrength,
        ]);
    }

    public function disableEncryption(): void
    {
        $this->update([
            'is_encrypted' => false,
            'encryption_algorithm' => null,
            'key_strength' => null,
        ]);
    }

    public function getEncryptionInfo(): array
    {
        return [
            'is_encrypted' => $this->is_encrypted,
            'algorithm' => $this->encryption_algorithm,
            'key_strength' => $this->key_strength,
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
        return $query->where('is_encrypted', true);
    }

    public function scopeUnencrypted($query)
    {
        return $query->where('is_encrypted', false);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'description', 'status', 'is_encrypted', 'encryption_algorithm', 'key_strength'])
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
