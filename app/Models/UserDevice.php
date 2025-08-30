<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserDevice extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'device_name',
        'device_type',
        'platform',
        'user_agent',
        'public_key',
        'device_fingerprint',
        'is_trusted',
        'is_active',
        'verified_at',
        'last_used_at',
        'last_key_rotation_at',
        'device_capabilities',
        'security_level',
        'encryption_version',
        'auto_trust_expires_at',
        'hardware_fingerprint',
        'device_info',
        'failed_auth_attempts',
        'locked_until',
    ];

    protected $casts = [
        'public_key' => 'json',
        'device_fingerprint' => 'json',
        'device_capabilities' => 'json',
        'hardware_fingerprint' => 'json',
        'device_info' => 'json',
        'is_trusted' => 'boolean',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'verified_at' => 'datetime',
        'last_key_rotation_at' => 'datetime',
        'auto_trust_expires_at' => 'datetime',
        'locked_until' => 'datetime',
    ];

    protected $hidden = [
        'verification_token',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($device) {
            // Set default values for new devices
            if (!isset($device->is_trusted)) {
                $device->is_trusted = false;
            }
            if (!isset($device->is_active)) {
                $device->is_active = true;
            }
        });
    }

    /**
     * Get the user that owns the device.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the device session.
     */
    public function session(): HasOne
    {
        return $this->hasOne(DeviceSession::class, 'user_device_id');
    }

    /**
     * Get cross-device messages sent from this device.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(CrossDeviceMessage::class, 'sender_device_id');
    }

    /**
     * Generate a unique device ID.
     */
    public static function generateDeviceId(): string
    {
        do {
            $deviceId = Str::random(32);
        } while (self::where('device_id', $deviceId)->exists());

        return $deviceId;
    }

    /**
     * Generate a verification token.
     */
    public static function generateVerificationToken(): string
    {
        return sprintf('%06d', random_int(100000, 999999));
    }

    /**
     * Check if the device is online.
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(5));
    }

    /**
     * Check if the device verification has expired.
     */
    public function isVerificationExpired(): bool
    {
        return $this->verification_expires_at && $this->verification_expires_at->lt(now());
    }

    /**
     * Update the last seen timestamp.
     */
    public function updateLastSeen(): void
    {
        $this->update([
            'last_seen_at' => now(),
            'last_ip' => request()->ip(),
        ]);
    }

    /**
     * Verify the device with a token.
     */
    public function verify(string $token): bool
    {
        if ($this->verification_token !== $token) {
            return false;
        }

        if ($this->isVerificationExpired()) {
            return false;
        }

        $this->update([
            'is_trusted' => true,
            'verification_status' => 'verified',
            'verified_at' => now(),
            'trust_level' => max($this->trust_level, 7),
            'verification_token' => null,
            'verification_expires_at' => null,
        ]);

        return true;
    }

    /**
     * Revoke the device.
     */
    public function revoke(): void
    {
        $this->update([
            'is_trusted' => false,
            'verification_status' => 'rejected',
            'trust_level' => 0,
        ]);

        // Deactivate sessions
        $this->session?->update(['is_active' => false]);
    }

    /**
     * Update trust level.
     */
    public function updateTrustLevel(int $level): void
    {
        $this->update([
            'trust_level' => max(0, min(10, $level)),
        ]);
    }

    /**
     * Update quantum security level.
     */
    public function updateQuantumSecurityLevel(int $level): void
    {
        $this->update([
            'quantum_security_level' => max(0, min(10, $level)),
        ]);
    }

    /**
     * Rotate device keys.
     */
    public function rotateKeys(array $newPublicKey, array $quantumKeyInfo): void
    {
        $this->update([
            'public_key' => $newPublicKey,
            'quantum_key_info' => $quantumKeyInfo,
            'last_key_rotation_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Get trusted devices for the user.
     */
    public static function getTrustedDevicesForUser(int $userId)
    {
        return self::where('user_id', $userId)
            ->where('is_trusted', true)
            ->where('verification_status', 'verified')
            ->orderBy('last_seen_at', 'desc')
            ->get();
    }

    /**
     * Get device security metrics.
     */
    public function getSecurityMetrics(): array
    {
        return [
            'device_id' => $this->device_id,
            'trust_level' => $this->trust_level,
            'quantum_security_level' => $this->quantum_security_level,
            'verification_status' => $this->verification_status,
            'is_online' => $this->isOnline(),
            'last_seen' => $this->last_seen_at?->toISOString(),
            'last_key_rotation' => $this->last_key_rotation_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    /**
     * Scope for trusted devices.
     */
    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true)
                    ->where('verification_status', 'verified');
    }

    /**
     * Scope for online devices.
     */
    public function scopeOnline($query)
    {
        return $query->where('last_seen_at', '>', now()->subMinutes(5));
    }

    /**
     * Scope for devices needing key rotation.
     */
    public function scopeNeedsKeyRotation($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_key_rotation_at')
              ->orWhere('last_key_rotation_at', '<', now()->subDay());
        });
    }

    /**
     * Get verification code for display.
     */
    public function getVerificationCode(): ?string
    {
        if ($this->verification_status !== 'pending' || $this->isVerificationExpired()) {
            return null;
        }

        return $this->verification_token;
    }

    /**
     * Check if device can be revoked.
     */
    public function canBeRevoked(): bool
    {
        return !$this->is_current_device && $this->is_trusted;
    }

    /**
     * Get human-readable device type.
     */
    public function getDeviceTypeDisplayAttribute(): string
    {
        return match ($this->device_type) {
            'desktop' => 'Desktop',
            'mobile' => 'Mobile',
            'tablet' => 'Tablet',
            'web' => 'Web Browser',
            default => 'Unknown',
        };
    }

    /**
     * Get human-readable verification status.
     */
    public function getVerificationStatusDisplayAttribute(): string
    {
        return match ($this->verification_status) {
            'pending' => 'Pending Verification',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'expired' => 'Verification Expired',
            default => 'Unknown',
        };
    }
}