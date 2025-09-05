<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UserDevice extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'user_devices';

    protected $fillable = [
        'user_id',
        'device_name',
        'device_type',
        'public_key',
        'device_fingerprint',
        'platform',
        'user_agent',
        'last_used_at',
        'is_trusted',
        'is_active',
        'verified_at',
        'device_capabilities',
        'security_level',
        'encryption_version',
        'auto_trust_expires_at',
        'hardware_fingerprint',
        'device_info',
        'last_key_rotation_at',
        'failed_auth_attempts',
        'locked_until',
        'preferred_algorithm',
        'quantum_health_score',
        'capabilities_verified_at',
        'last_quantum_health_check',
        'encryption_capabilities',
        'quantum_ready',
        'trust_level',
        'revoked_at',
        'revocation_reason',
    ];

    protected $casts = [
        'device_capabilities' => 'array',
        'device_info' => 'array',
        'encryption_capabilities' => 'array',
        'last_used_at' => 'datetime',
        'verified_at' => 'datetime',
        'auto_trust_expires_at' => 'datetime',
        'last_key_rotation_at' => 'datetime',
        'capabilities_verified_at' => 'datetime',
        'last_quantum_health_check' => 'datetime',
        'locked_until' => 'datetime',
        'revoked_at' => 'datetime',
        'is_trusted' => 'boolean',
        'is_active' => 'boolean',
        'quantum_ready' => 'boolean',
        'encryption_version' => 'integer',
        'failed_auth_attempts' => 'integer',
        'quantum_health_score' => 'integer',
    ];

    protected $attributes = [
        'device_type' => 'unknown',
        'is_trusted' => false,
        'is_active' => true,
        'security_level' => 'medium',
        'encryption_version' => 2,
        'failed_auth_attempts' => 0,
        'quantum_health_score' => 100,
        'quantum_ready' => false,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'device_name', 'device_type', 'is_trusted', 'is_active', 'security_level',
                'encryption_version', 'quantum_ready', 'trust_level', 'revoked_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Device {$eventName}")
            ->useLogName('user_device');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function encryptionKeys(): HasMany
    {
        return $this->hasMany(\App\Models\Chat\EncryptionKey::class, 'device_id');
    }

    public function signalIdentityKeys(): HasMany
    {
        return $this->hasMany(\App\Models\Signal\IdentityKey::class, 'device_id');
    }

    public function signalSessions(): HasMany
    {
        return $this->hasMany(\App\Models\Signal\Session::class, 'local_device_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('revoked_at');
    }

    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true);
    }

    public function scopeUntrusted($query)
    {
        return $query->where('is_trusted', false);
    }

    public function scopeQuantumReady($query)
    {
        return $query->where('quantum_ready', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('device_type', $type);
    }

    public function scopeBySecurityLevel($query, $level)
    {
        return $query->where('security_level', $level);
    }

    public function scopeRevoked($query)
    {
        return $query->whereNotNull('revoked_at');
    }

    public function scopeLocked($query)
    {
        return $query->where('locked_until', '>', now());
    }

    public function scopeUnlocked($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('locked_until')->orWhere('locked_until', '<=', now());
        });
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active && ! $this->isRevoked() && ! $this->isLocked();
    }

    public function isTrusted(): bool
    {
        return $this->is_trusted;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function isQuantumReady(): bool
    {
        return $this->quantum_ready;
    }

    public function isMobile(): bool
    {
        return in_array($this->device_type, ['mobile', 'tablet']);
    }

    public function isDesktop(): bool
    {
        return $this->device_type === 'desktop';
    }

    public function isWeb(): bool
    {
        return $this->device_type === 'web';
    }

    public function trust(bool $autoExpire = true): void
    {
        $updates = [
            'is_trusted' => true,
            'verified_at' => now(),
            'trust_level' => 'trusted',
        ];

        if ($autoExpire) {
            $updates['auto_trust_expires_at'] = now()->addDays(30);
        }

        $this->update($updates);
    }

    public function untrust(?string $reason = null): void
    {
        $this->update([
            'is_trusted' => false,
            'trust_level' => 'untrusted',
            'auto_trust_expires_at' => null,
        ]);

        if ($reason) {
            $this->update(['revocation_reason' => $reason]);
        }
    }

    public function revoke(?string $reason = null): void
    {
        $this->update([
            'is_active' => false,
            'is_trusted' => false,
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);
    }

    public function lock(int $minutes = 60, ?string $reason = null): void
    {
        $this->update([
            'locked_until' => now()->addMinutes($minutes),
            'revocation_reason' => $reason,
        ]);
    }

    public function unlock(): void
    {
        $this->update([
            'locked_until' => null,
            'failed_auth_attempts' => 0,
        ]);
    }

    public function incrementFailedAttempts(): void
    {
        $attempts = $this->failed_auth_attempts + 1;
        $updates = ['failed_auth_attempts' => $attempts];

        // Lock device after 5 failed attempts
        if ($attempts >= 5) {
            $updates['locked_until'] = now()->addHours(1);
        }

        $this->update($updates);
    }

    public function resetFailedAttempts(): void
    {
        $this->update(['failed_auth_attempts' => 0]);
    }

    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function updateQuantumHealth(int $score): void
    {
        $this->update([
            'quantum_health_score' => $score,
            'last_quantum_health_check' => now(),
        ]);
    }

    public function markQuantumReady(array $capabilities = []): void
    {
        $this->update([
            'quantum_ready' => true,
            'encryption_capabilities' => array_merge($this->encryption_capabilities ?? [], $capabilities),
            'capabilities_verified_at' => now(),
        ]);
    }

    public function getSecurityRisk(): string
    {
        if ($this->isRevoked()) {
            return 'high';
        }

        if (! $this->isTrusted()) {
            return 'medium';
        }

        if ($this->failed_auth_attempts > 0) {
            return 'low';
        }

        if (! $this->last_used_at || $this->last_used_at->diffInDays(now()) > 30) {
            return 'medium';
        }

        return 'low';
    }

    public function supportsAlgorithm(string $algorithm): bool
    {
        $capabilities = $this->encryption_capabilities ?? [];

        return in_array($algorithm, $capabilities) ||
               ($this->quantum_ready && str_contains($algorithm, 'ML-KEM'));
    }

    public function getPreferredAlgorithm(): string
    {
        if ($this->preferred_algorithm) {
            return $this->preferred_algorithm;
        }

        if ($this->quantum_ready) {
            return 'ML-KEM-768';
        }

        return 'RSA-4096-OAEP';
    }

    public function needsKeyRotation(): bool
    {
        if (! $this->last_key_rotation_at) {
            return true;
        }

        return $this->last_key_rotation_at->diffInDays(now()) > 90;
    }
}
