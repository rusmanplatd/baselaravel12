<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SignalOnetimePrekey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'key_id',
        'public_key',
        'private_key_encrypted',
        'is_used',
        'used_at',
        'used_by_user_id',
    ];

    protected $casts = [
        'key_id' => 'integer',
        'is_used' => 'boolean',
        'used_at' => 'datetime',
        'used_by_user_id' => 'integer',
    ];

    /**
     * Get the user that owns this one-time prekey.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who used this prekey.
     */
    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    /**
     * Get an unused one-time prekey for a user.
     */
    public static function getUnusedForUser(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('is_used', false)
            ->oldest()
            ->first();
    }

    /**
     * Get count of unused prekeys for a user.
     */
    public static function getUnusedCountForUser(int $userId): int
    {
        return self::where('user_id', $userId)
            ->where('is_used', false)
            ->count();
    }

    /**
     * Mark this prekey as used.
     */
    public function markAsUsed(int $usedByUserId): bool
    {
        return $this->update([
            'is_used' => true,
            'used_at' => now(),
            'used_by_user_id' => $usedByUserId,
        ]);
    }

    /**
     * Generate a new key ID for a user.
     */
    public static function generateKeyId(int $userId): int
    {
        $latestKey = self::where('user_id', $userId)
            ->orderBy('key_id', 'desc')
            ->first();

        return $latestKey ? $latestKey->key_id + 1 : 1;
    }

    /**
     * Generate multiple key IDs for batch creation.
     */
    public static function generateKeyIds(int $userId, int $count): array
    {
        $latestKey = self::where('user_id', $userId)
            ->orderBy('key_id', 'desc')
            ->first();

        $startId = $latestKey ? $latestKey->key_id + 1 : 1;
        
        return range($startId, $startId + $count - 1);
    }

    /**
     * Clean up used prekeys older than specified days.
     */
    public static function cleanupUsedKeys(int $userId, int $olderThanDays = 30): int
    {
        return self::where('user_id', $userId)
            ->where('is_used', true)
            ->where('used_at', '<', now()->subDays($olderThanDays))
            ->delete();
    }
}