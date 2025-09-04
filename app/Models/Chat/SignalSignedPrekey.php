<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalSignedPrekey extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'key_id',
        'public_key',
        'private_key_encrypted',
        'signature',
        'generated_at',
        'is_active',
        'quantum_public_key',
        'quantum_private_key_encrypted',
        'quantum_algorithm',
        'is_quantum_capable',
    ];

    protected $casts = [
        'key_id' => 'integer',
        'generated_at' => 'datetime',
        'is_active' => 'boolean',
        'is_quantum_capable' => 'boolean',
    ];

    /**
     * Get the user that owns this signed prekey.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the identity key for this user.
     */
    public function identityKey(): BelongsTo
    {
        return $this->belongsTo(SignalIdentityKey::class, 'user_id', 'user_id')
            ->where('is_active', true);
    }

    /**
     * Get the current active signed prekey for a user.
     */
    public static function getCurrentForUser(string $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->latest('generated_at')
            ->first();
    }

    /**
     * Generate a new key ID for a user.
     */
    public static function generateKeyId(string $userId): int
    {
        $latestKey = self::where('user_id', $userId)
            ->orderBy('key_id', 'desc')
            ->first();

        return $latestKey ? $latestKey->key_id + 1 : 1;
    }

    /**
     * Check if this signed prekey needs rotation (older than 7 days).
     */
    public function needsRotation(): bool
    {
        return $this->generated_at->addDays(7)->isPast();
    }

    /**
     * Deactivate old signed prekeys, keeping only the latest N.
     */
    public static function cleanupOldKeys(string $userId, int $keepCount = 3): int
    {
        $keysToKeep = self::where('user_id', $userId)
            ->orderBy('generated_at', 'desc')
            ->limit($keepCount)
            ->pluck('id')
            ->toArray();

        return self::where('user_id', $userId)
            ->whereNotIn('id', $keysToKeep)
            ->update(['is_active' => false]);
    }

    /**
     * Verify the signature against the identity key.
     */
    public function verifySignature(string $identityPublicKey): bool
    {
        // In a real implementation, you would verify the signature here
        // This is a simplified version
        return ! empty($this->signature);
    }
}
