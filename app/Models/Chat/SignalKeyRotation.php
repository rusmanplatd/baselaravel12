<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SignalKeyRotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'rotation_type',
        'old_key_id',
        'new_key_id',
        'reason',
        'rotation_metadata',
    ];

    protected $casts = [
        'rotation_metadata' => 'array',
    ];

    /**
     * Get the user who performed the key rotation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the session this rotation belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SignalSession::class);
    }

    /**
     * Get rotation statistics for a user.
     */
    public static function getUserRotationStats(int $userId): array
    {
        $rotations = self::where('user_id', $userId);

        return [
            'total_rotations' => $rotations->count(),
            'identity_rotations' => $rotations->where('rotation_type', 'identity')->count(),
            'signed_prekey_rotations' => $rotations->where('rotation_type', 'signed_prekey')->count(),
            'onetime_prekey_rotations' => $rotations->where('rotation_type', 'onetime_prekeys')->count(),
            'session_key_rotations' => $rotations->where('rotation_type', 'session_keys')->count(),
            'last_rotation' => $rotations->latest()->first()?->created_at,
        ];
    }

    /**
     * Get recent rotations for monitoring.
     */
    public static function getRecentRotations(int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('created_at', '>=', now()->subHours($hours))
            ->with(['user:id,name', 'session:id,session_id'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}