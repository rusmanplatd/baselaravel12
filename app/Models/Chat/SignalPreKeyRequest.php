<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalPreKeyRequest extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'signal_prekey_requests';

    protected $fillable = [
        'requester_user_id',
        'target_user_id',
        'identity_key_id',
        'signed_prekey_id',
        'onetime_prekey_id',
        'request_id',
        'bundle_data',
        'is_consumed',
        'consumed_at',
    ];

    protected $casts = [
        'bundle_data' => 'array',
        'is_consumed' => 'boolean',
        'consumed_at' => 'datetime',
    ];

    /**
     * Get the user who requested the prekey bundle.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * Get the target user whose prekey bundle was requested.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Get the identity key.
     */
    public function identityKey(): BelongsTo
    {
        return $this->belongsTo(SignalIdentityKey::class);
    }

    /**
     * Get the signed prekey.
     */
    public function signedPrekey(): BelongsTo
    {
        return $this->belongsTo(SignalSignedPrekey::class);
    }

    /**
     * Get the one-time prekey.
     */
    public function onetimePrekey(): BelongsTo
    {
        return $this->belongsTo(SignalOnetimePrekey::class);
    }

    /**
     * Mark the request as consumed.
     */
    public function markConsumed(): bool
    {
        return $this->update([
            'is_consumed' => true,
            'consumed_at' => now(),
        ]);
    }

    /**
     * Get unconsumed requests for a user.
     */
    public static function getUnconsumedForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('target_user_id', $userId)
            ->where('is_consumed', false)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Clean up old consumed requests.
     */
    public static function cleanupOldRequests(int $olderThanDays = 30): int
    {
        return self::where('is_consumed', true)
            ->where('consumed_at', '<', now()->subDays($olderThanDays))
            ->delete();
    }
}
