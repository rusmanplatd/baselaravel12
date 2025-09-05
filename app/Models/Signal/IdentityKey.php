<?php

namespace App\Models\Signal;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class IdentityKey extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'signal_identity_keys';

    protected $fillable = [
        'user_id',
        'registration_id',
        'public_key',
        'private_key_encrypted',
        'key_fingerprint',
        'is_active',
        'quantum_public_key',
        'quantum_private_key_encrypted',
        'quantum_algorithm',
        'is_quantum_capable',
        'quantum_version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_quantum_capable' => 'boolean',
        'registration_id' => 'integer',
        'quantum_version' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_quantum_capable' => false,
        'quantum_version' => 1,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['registration_id', 'is_active', 'quantum_algorithm', 'is_quantum_capable', 'quantum_version'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Signal Identity Key {$eventName}")
            ->useLogName('signal');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeQuantumCapable($query)
    {
        return $query->where('is_quantum_capable', true);
    }

    public function scopeClassical($query)
    {
        return $query->where('is_quantum_capable', false);
    }

    public function scopeByAlgorithm($query, $algorithm)
    {
        return $query->where('quantum_algorithm', $algorithm);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isQuantumCapable(): bool
    {
        return $this->is_quantum_capable && ! empty($this->quantum_public_key);
    }

    public function isClassical(): bool
    {
        return ! $this->isQuantumCapable();
    }

    public function isHybrid(): bool
    {
        return $this->isQuantumCapable() && ! empty($this->public_key);
    }

    public function getFingerprint(): string
    {
        return $this->key_fingerprint;
    }

    public function getShortFingerprint(): string
    {
        return substr($this->key_fingerprint, 0, 8);
    }

    public function enableQuantum(string $publicKey, string $privateKeyEncrypted, string $algorithm): void
    {
        $this->update([
            'quantum_public_key' => $publicKey,
            'quantum_private_key_encrypted' => $privateKeyEncrypted,
            'quantum_algorithm' => $algorithm,
            'is_quantum_capable' => true,
        ]);
    }

    public function disableQuantum(): void
    {
        $this->update([
            'quantum_public_key' => null,
            'quantum_private_key_encrypted' => null,
            'quantum_algorithm' => null,
            'is_quantum_capable' => false,
        ]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function rotate(string $newPublicKey, string $newPrivateKeyEncrypted, string $newFingerprint): static
    {
        // Deactivate current key
        $this->deactivate();

        // Create new key
        return static::create([
            'user_id' => $this->user_id,
            'registration_id' => $this->registration_id + 1,
            'public_key' => $newPublicKey,
            'private_key_encrypted' => $newPrivateKeyEncrypted,
            'key_fingerprint' => $newFingerprint,
            'is_active' => true,
            'quantum_public_key' => $this->quantum_public_key,
            'quantum_private_key_encrypted' => $this->quantum_private_key_encrypted,
            'quantum_algorithm' => $this->quantum_algorithm,
            'is_quantum_capable' => $this->is_quantum_capable,
            'quantum_version' => $this->quantum_version,
        ]);
    }

    public function getSupportedAlgorithms(): array
    {
        $algorithms = ['Ed25519'];

        if ($this->isQuantumCapable()) {
            $algorithms[] = $this->quantum_algorithm;
            $algorithms[] = 'HYBRID-'.$this->quantum_algorithm;
        }

        return $algorithms;
    }

    public function getBestAlgorithmForPeer(IdentityKey $peerKey): string
    {
        // Both quantum capable - use quantum
        if ($this->isQuantumCapable() && $peerKey->isQuantumCapable()) {
            if ($this->quantum_algorithm === $peerKey->quantum_algorithm) {
                return $this->quantum_algorithm;
            }

            // Different quantum algorithms - use hybrid
            return "HYBRID-{$this->quantum_algorithm}";
        }

        // Only one quantum capable - use hybrid
        if ($this->isQuantumCapable() || $peerKey->isQuantumCapable()) {
            $quantumAlgorithm = $this->quantum_algorithm ?? $peerKey->quantum_algorithm;

            return "HYBRID-{$quantumAlgorithm}";
        }

        // Both classical - use Ed25519
        return 'Ed25519';
    }
}
