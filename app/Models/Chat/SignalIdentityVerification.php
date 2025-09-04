<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SignalIdentityVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'verifier_user_id',
        'target_user_id',
        'session_id',
        'verification_method',
        'provided_fingerprint',
        'actual_fingerprint',
        'verification_successful',
        'verification_token',
        'verification_metadata',
    ];

    protected $casts = [
        'verification_successful' => 'boolean',
        'verification_metadata' => 'array',
    ];

    /**
     * Get the user who performed the verification.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_user_id');
    }

    /**
     * Get the user whose identity was verified.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Get the session this verification relates to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SignalSession::class);
    }

    /**
     * Get successful verifications for a user.
     */
    public static function getSuccessfulVerificationsForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('verifier_user_id', $userId)
            ->where('verification_successful', true)
            ->with(['targetUser:id,name', 'session:id,session_id'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if users have verified each other.
     */
    public static function areUsersVerified(int $user1Id, int $user2Id): bool
    {
        return self::where(function ($query) use ($user1Id, $user2Id) {
            $query->where('verifier_user_id', $user1Id)
                  ->where('target_user_id', $user2Id);
        })
        ->orWhere(function ($query) use ($user1Id, $user2Id) {
            $query->where('verifier_user_id', $user2Id)
                  ->where('target_user_id', $user1Id);
        })
        ->where('verification_successful', true)
        ->exists();
    }

    /**
     * Get verification statistics.
     */
    public static function getVerificationStats(): array
    {
        return [
            'total_attempts' => self::count(),
            'successful_verifications' => self::where('verification_successful', true)->count(),
            'failed_verifications' => self::where('verification_successful', false)->count(),
            'unique_verified_pairs' => self::where('verification_successful', true)
                ->selectRaw('COUNT(DISTINCT CONCAT(LEAST(verifier_user_id, target_user_id), "-", GREATEST(verifier_user_id, target_user_id))) as pairs')
                ->value('pairs'),
            'verification_methods' => self::where('verification_successful', true)
                ->selectRaw('verification_method, COUNT(*) as count')
                ->groupBy('verification_method')
                ->pluck('count', 'verification_method')
                ->toArray(),
        ];
    }
}