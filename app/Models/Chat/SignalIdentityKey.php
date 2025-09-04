<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SignalIdentityKey extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'registration_id',
        'public_key',
        'private_key_encrypted',
        'key_fingerprint',
        'is_active',
    ];

    protected $casts = [
        'registration_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns this identity key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the signed prekeys for this identity key.
     */
    public function signedPrekeys(): HasMany
    {
        return $this->hasMany(SignalSignedPrekey::class, 'user_id', 'user_id');
    }

    /**
     * Get the one-time prekeys for this identity key.
     */
    public function onetimePrekeys(): HasMany
    {
        return $this->hasMany(SignalOnetimePrekey::class, 'user_id', 'user_id');
    }

    /**
     * Calculate fingerprint from public key.
     */
    public static function calculateFingerprint(string $publicKey): string
    {
        return hash('sha256', base64_decode($publicKey));
    }

    /**
     * Verify the fingerprint matches the public key.
     */
    public function verifyFingerprint(string $expectedFingerprint): bool
    {
        return hash_equals($this->key_fingerprint, $expectedFingerprint);
    }

    /**
     * Get the current active identity key for a user.
     */
    public static function getCurrentForUser(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    /**
     * Generate a unique registration ID.
     */
    public static function generateRegistrationId(): int
    {
        do {
            $registrationId = rand(1, 16384);
        } while (self::where('registration_id', $registrationId)->exists());

        return $registrationId;
    }
}
