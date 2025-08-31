<?php

namespace App\Models;

use App\Models\Chat\DeviceKeyShare;
use App\Models\Chat\EncryptionKey;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserDevice extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'device_name',
        'device_type',
        'public_key',
        'device_fingerprint',
        'hardware_fingerprint',
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
        'device_info',
        'failed_auth_attempts',
        'locked_until',
        'last_key_rotation_at',
        'capabilities_verified_at',
        'last_quantum_health_check',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'verified_at' => 'datetime',
        'auto_trust_expires_at' => 'datetime',
        'locked_until' => 'datetime',
        'last_key_rotation_at' => 'datetime',
        'capabilities_verified_at' => 'datetime',
        'last_quantum_health_check' => 'datetime',
        'is_trusted' => 'boolean',
        'is_active' => 'boolean',
        'device_capabilities' => 'array',
        'device_info' => 'array',
        'failed_auth_attempts' => 'integer',
    ];

    protected $hidden = [
        'public_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function encryptionKeys(): HasMany
    {
        return $this->hasMany(EncryptionKey::class, 'device_id');
    }

    public function createdEncryptionKeys(): HasMany
    {
        return $this->hasMany(EncryptionKey::class, 'created_by_device_id');
    }

    public function outgoingKeyShares(): HasMany
    {
        return $this->hasMany(DeviceKeyShare::class, 'from_device_id');
    }

    public function incomingKeyShares(): HasMany
    {
        return $this->hasMany(DeviceKeyShare::class, 'to_device_id');
    }

    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function markAsTrusted(): void
    {
        $this->update([
            'is_trusted' => true,
            'verified_at' => now(),
        ]);
    }

    public function deactivate(): void
    {
        DB::transaction(function () {
            $this->update([
                'is_active' => false,
                'is_trusted' => false,
            ]);

            // Deactivate all encryption keys for this device
            $this->encryptionKeys()->update(['is_active' => false]);

            // Cancel pending key shares
            $this->outgoingKeyShares()->where('is_accepted', false)->update(['is_active' => false]);
            $this->incomingKeyShares()->where('is_accepted', false)->update(['is_active' => false]);

            Log::info('Device deactivated', [
                'device_id' => $this->id,
                'user_id' => $this->user_id,
                'device_name' => $this->device_name,
            ]);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByFingerprint($query, string $fingerprint)
    {
        return $query->where('device_fingerprint', $fingerprint);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->device_name ?: ($this->platform ? ucfirst($this->platform).' Device' : 'Unknown Device');
    }

    public function getShortFingerprintAttribute(): string
    {
        return substr($this->device_fingerprint, 0, 8).'...';
    }

    public function canAccessConversation(string $conversationId): bool
    {
        if (! $this->is_active || ! $this->is_trusted) {
            return false;
        }

        return $this->encryptionKeys()
            ->where('conversation_id', $conversationId)
            ->where('is_active', true)
            ->exists();
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->device_capabilities ?? []);
    }

    public function requiresKeyRotation(): bool
    {
        // Check if device has old encryption version
        if ($this->encryption_version && $this->encryption_version < 2) {
            return true;
        }

        // Check if device hasn't been used recently
        if ($this->last_used_at && $this->last_used_at->diffInDays(now()) > 90) {
            return true;
        }

        return false;
    }

    public function isAutoTrustExpired(): bool
    {
        return $this->auto_trust_expires_at && $this->auto_trust_expires_at->isPast();
    }

    public function getSecurityScore(): int
    {
        $score = 0;

        // Base score for active trusted device
        if ($this->is_trusted && $this->is_active) {
            $score += 50;
        }

        // Bonus for verification
        if ($this->verified_at) {
            $score += 20;
        }

        // Bonus for recent usage
        if ($this->last_used_at && $this->last_used_at->diffInDays(now()) < 7) {
            $score += 15;
        }

        // Bonus for modern encryption
        if ($this->encryption_version >= 2) {
            $score += 10;
        }

        // Penalty for old devices
        if ($this->created_at->diffInMonths(now()) > 6) {
            $score -= 5;
        }

        return min(100, max(0, $score));
    }

    /**
     * Check if device supports quantum-resistant algorithms
     */
    public function supportsQuantumResistant(): bool
    {
        $capabilities = $this->device_capabilities ?? [];
        $quantumAlgorithms = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024', 'hybrid'];
        
        return !empty(array_intersect($capabilities, $quantumAlgorithms));
    }

    /**
     * Get supported quantum algorithms
     */
    public function getQuantumCapabilities(): array
    {
        $capabilities = $this->device_capabilities ?? [];
        $quantumAlgorithms = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024', 'hybrid'];
        
        return array_intersect($capabilities, $quantumAlgorithms);
    }

    /**
     * Check if device supports specific algorithm
     */
    public function supportsAlgorithm(string $algorithm): bool
    {
        $algorithmMap = [
            'RSA-4096-OAEP' => 'rsa-4096',
            'ML-KEM-512' => 'ml-kem-512',
            'ML-KEM-768' => 'ml-kem-768',
            'ML-KEM-1024' => 'ml-kem-1024',
            'HYBRID-RSA4096-MLKEM768' => 'hybrid',
        ];
        
        $capability = $algorithmMap[$algorithm] ?? strtolower($algorithm);
        $capabilities = $this->device_capabilities ?? [];
        
        return in_array($capability, $capabilities);
    }

    /**
     * Update device quantum capabilities
     */
    public function updateQuantumCapabilities(array $newCapabilities): void
    {
        $existingCapabilities = $this->device_capabilities ?? [];
        $quantumAlgorithms = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024', 'hybrid'];
        
        // Remove old quantum capabilities
        $nonQuantumCapabilities = array_diff($existingCapabilities, $quantumAlgorithms);
        
        // Add new quantum capabilities
        $updatedCapabilities = array_merge($nonQuantumCapabilities, $newCapabilities);
        
        $this->update([
            'device_capabilities' => array_unique($updatedCapabilities),
            'encryption_version' => $this->determineEncryptionVersion($newCapabilities),
        ]);
    }

    /**
     * Get all supported algorithms for this device
     */
    public function getSupportedAlgorithms(): array
    {
        $capabilities = $this->device_capabilities ?? [];
        $algorithmMap = [
            'rsa-4096' => 'RSA-4096-OAEP',
            'ml-kem-512' => 'ML-KEM-512',
            'ml-kem-768' => 'ML-KEM-768',
            'ml-kem-1024' => 'ML-KEM-1024',
            'hybrid' => 'HYBRID-RSA4096-MLKEM768',
        ];
        
        $algorithms = [];
        foreach ($capabilities as $cap) {
            if (isset($algorithmMap[$cap])) {
                $algorithms[] = $algorithmMap[$cap];
            }
        }
        
        return $algorithms ?: ['RSA-4096-OAEP']; // Fallback to RSA
    }

    /**
     * Check if device is quantum-ready
     */
    public function isQuantumReady(): bool
    {
        if ($this->encryption_version < 3) {
            return false;
        }
        
        $capabilities = $this->device_capabilities ?? [];
        $pureQuantumAlgorithms = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024'];
        
        // Device is quantum ready only if it has pure ML-KEM capabilities
        // Hybrid capabilities alone don't make it quantum ready
        return !empty(array_intersect($capabilities, $pureQuantumAlgorithms));
    }

    /**
     * Determine encryption version based on capabilities
     */
    private function determineEncryptionVersion(array $capabilities): int
    {
        $quantumCapabilities = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024', 'hybrid'];
        
        if (array_intersect($capabilities, $quantumCapabilities)) {
            return 3; // Quantum-resistant version
        }
        
        return 2; // RSA version
    }
}
