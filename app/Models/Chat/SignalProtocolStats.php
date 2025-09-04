<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalProtocolStats extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'active_sessions',
        'total_messages_sent',
        'total_messages_received',
        'key_rotations_performed',
        'identity_verifications',
        'prekey_refreshes',
        'available_onetime_prekeys',
        'last_prekey_refresh',
        'last_session_activity',
        'health_metrics',
    ];

    protected $casts = [
        'active_sessions' => 'integer',
        'total_messages_sent' => 'integer',
        'total_messages_received' => 'integer',
        'key_rotations_performed' => 'integer',
        'identity_verifications' => 'integer',
        'prekey_refreshes' => 'integer',
        'available_onetime_prekeys' => 'integer',
        'last_prekey_refresh' => 'datetime',
        'last_session_activity' => 'datetime',
        'health_metrics' => 'array',
    ];

    /**
     * Get the user these stats belong to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get aggregated statistics for all users.
     */
    public static function getGlobalStats(): array
    {
        return [
            'total_users' => self::count(),
            'active_users' => self::whereNotNull('last_session_activity')
                ->where('last_session_activity', '>=', now()->subDays(30))
                ->count(),
            'total_sessions' => self::sum('active_sessions'),
            'total_messages' => self::sum('total_messages_sent'),
            'total_verifications' => self::sum('identity_verifications'),
            'total_key_rotations' => self::sum('key_rotations_performed'),
            'average_prekeys_per_user' => self::avg('available_onetime_prekeys'),
            'users_needing_prekey_refresh' => self::where('available_onetime_prekeys', '<', 10)->count(),
        ];
    }

    /**
     * Get users who need maintenance.
     */
    public static function getUsersNeedingMaintenance(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where(function ($query) {
            $query->where('available_onetime_prekeys', '<', 10)
                ->orWhere('last_prekey_refresh', '<', now()->subDays(7))
                ->orWhereNull('last_prekey_refresh');
        })
            ->with('user:id,name,email')
            ->get();
    }

    /**
     * Update health metrics for a user.
     */
    public function updateHealthMetrics(): void
    {
        $healthScore = 100;
        $issues = [];

        // Check prekey availability
        if ($this->available_onetime_prekeys < 5) {
            $healthScore -= 20;
            $issues[] = 'Very low prekey count';
        } elseif ($this->available_onetime_prekeys < 15) {
            $healthScore -= 10;
            $issues[] = 'Low prekey count';
        }

        // Check activity
        if ($this->last_session_activity && $this->last_session_activity->addDays(30)->isPast()) {
            $healthScore -= 15;
            $issues[] = 'Inactive for 30+ days';
        }

        // Check prekey refresh
        if (! $this->last_prekey_refresh || $this->last_prekey_refresh->addDays(14)->isPast()) {
            $healthScore -= 10;
            $issues[] = 'Prekeys need refresh';
        }

        $this->update([
            'health_metrics' => [
                'score' => max(0, $healthScore),
                'status' => $healthScore >= 80 ? 'healthy' : ($healthScore >= 60 ? 'warning' : 'critical'),
                'issues' => $issues,
                'last_calculated' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get health distribution.
     */
    public static function getHealthDistribution(): array
    {
        $stats = self::whereNotNull('health_metrics')->get();

        $distribution = ['healthy' => 0, 'warning' => 0, 'critical' => 0];

        foreach ($stats as $stat) {
            $status = $stat->health_metrics['status'] ?? 'unknown';
            if (isset($distribution[$status])) {
                $distribution[$status]++;
            }
        }

        return $distribution;
    }
}
