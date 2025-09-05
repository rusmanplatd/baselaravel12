<?php

namespace App\Models\Chat;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EncryptionKey extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'chat_encryption_keys';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'device_id',
        'encrypted_key',
        'public_key',
        'device_fingerprint',
        'key_version',
        'created_by_device_id',
        'algorithm',
        'key_strength',
        'expires_at',
        'is_active',
        'last_used_at',
        'device_metadata',
        'revoked_at',
        'revocation_reason',
    ];

    protected $casts = [
        'device_metadata' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'key_version' => 'integer',
        'key_strength' => 'integer',
    ];

    protected $attributes = [
        'algorithm' => 'RSA-4096-OAEP',
        'key_strength' => 4096,
        'key_version' => 1,
        'is_active' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['algorithm', 'key_strength', 'key_version', 'is_active', 'revoked_at', 'revocation_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Encryption Key {$eventName}")
            ->useLogName('chat_encryption');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function createdByDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'created_by_device_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('revoked_at');
    }

    public function scopeRevoked($query)
    {
        return $query->whereNotNull('revoked_at');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeValid($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeByAlgorithm($query, $algorithm)
    {
        return $query->where('algorithm', $algorithm);
    }

    public function scopeForConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeByVersion($query, $version)
    {
        return $query->where('key_version', $version);
    }

    public function scopeLatestVersion($query)
    {
        return $query->orderByDesc('key_version');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active && ! $this->isRevoked() && ! $this->isExpired();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->isActive() && ! $this->isExpired();
    }

    public function isRSA(): bool
    {
        return str_contains($this->algorithm, 'RSA');
    }

    public function isQuantum(): bool
    {
        return str_contains($this->algorithm, 'ML-KEM') ||
               str_contains($this->algorithm, 'CRYSTALS') ||
               str_contains($this->algorithm, 'QUANTUM');
    }

    public function isHybrid(): bool
    {
        return str_contains($this->algorithm, 'HYBRID') ||
               str_contains($this->algorithm, '+');
    }

    public function revoke(?string $reason = null): void
    {
        $this->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);
    }

    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function rotate(string $newEncryptedKey, string $newPublicKey, array $options = []): static
    {
        // Create new key with incremented version
        return static::create([
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'device_id' => $this->device_id,
            'encrypted_key' => $newEncryptedKey,
            'public_key' => $newPublicKey,
            'device_fingerprint' => $this->device_fingerprint,
            'key_version' => $this->key_version + 1,
            'created_by_device_id' => $options['created_by_device_id'] ?? $this->device_id,
            'algorithm' => $options['algorithm'] ?? $this->algorithm,
            'key_strength' => $options['key_strength'] ?? $this->key_strength,
            'expires_at' => $options['expires_at'] ?? $this->expires_at,
            'device_metadata' => $options['device_metadata'] ?? $this->device_metadata,
        ]);
    }

    public function getSecurityLevel(): string
    {
        if ($this->isQuantum()) {
            return 'quantum-resistant';
        }

        if ($this->isHybrid()) {
            return 'hybrid';
        }

        if ($this->key_strength >= 4096) {
            return 'high';
        }

        if ($this->key_strength >= 2048) {
            return 'medium';
        }

        return 'low';
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }

        return max(0, now()->diffInDays($this->expires_at, false));
    }

    public function shouldRotate(): bool
    {
        // Rotate if key is close to expiry (within 7 days)
        $daysUntilExpiry = $this->getDaysUntilExpiry();
        if ($daysUntilExpiry !== null && $daysUntilExpiry <= 7) {
            return true;
        }

        // Rotate if key hasn't been used for 30 days
        if ($this->last_used_at && $this->last_used_at->diffInDays(now()) > 30) {
            return true;
        }

        // Rotate if using outdated algorithm
        if (! $this->isQuantum() && ! $this->isHybrid()) {
            return true;
        }

        return false;
    }
}
