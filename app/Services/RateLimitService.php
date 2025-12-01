<?php

namespace App\Services;

use App\Models\Chat\IpRestriction;
use App\Models\Chat\RateLimit;
use App\Models\Chat\RateLimitConfig;
use App\Models\Chat\UserPenalty;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitService
{
    private const CACHE_PREFIX = 'rate_limit:';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Check if an action is rate limited
     */
    public function checkRateLimit(
        string $action,
        string $key,
        ?User $user = null,
        ?string $ip = null
    ): array {
        try {
            // Check user penalties first
            if ($user && $this->hasActivePenalty($user, $action)) {
                return [
                    'allowed' => false,
                    'reason' => 'user_penalty',
                    'message' => 'User has active restrictions',
                    'retry_after' => $this->getUserPenaltyDuration($user, $action),
                ];
            }

            // Check IP restrictions
            if ($ip && $this->hasIpRestriction($ip)) {
                return [
                    'allowed' => false,
                    'reason' => 'ip_restriction',
                    'message' => 'IP address is restricted',
                    'retry_after' => $this->getIpRestrictionDuration($ip),
                ];
            }

            // Get rate limit configuration
            $config = $this->getRateLimitConfig($action);
            if (! $config) {
                return ['allowed' => true]; // No rate limit configured
            }

            // Check current rate limit status
            $currentLimit = $this->getCurrentRateLimit($key, $action, $config);

            if ($currentLimit['hits'] >= $config->max_attempts) {
                return [
                    'allowed' => false,
                    'reason' => 'rate_limit_exceeded',
                    'message' => 'Rate limit exceeded',
                    'retry_after' => $currentLimit['reset_at'],
                    'current_hits' => $currentLimit['hits'],
                    'max_attempts' => $config->max_attempts,
                    'window_seconds' => $config->window_seconds,
                ];
            }

            return [
                'allowed' => true,
                'current_hits' => $currentLimit['hits'],
                'max_attempts' => $config->max_attempts,
                'window_seconds' => $config->window_seconds,
                'reset_at' => $currentLimit['reset_at'],
            ];

        } catch (Exception $e) {
            Log::error('Rate limit check failed', [
                'action' => $action,
                'key' => $key,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);

            // Fail open - allow action on error
            return ['allowed' => true];
        }
    }

    /**
     * Record a hit for rate limiting (alias for recordAction)
     */
    public function recordHit(
        string $action,
        string $key,
        ?User $user = null,
        ?string $ip = null,
        array $metadata = []
    ): bool {
        return $this->recordAction($action, $key, $user, $ip, $metadata);
    }

    /**
     * Record a rate limited action
     */
    public function recordAction(
        string $action,
        string $key,
        ?User $user = null,
        ?string $ip = null,
        array $metadata = []
    ): bool {
        try {
            $config = $this->getRateLimitConfig($action);
            if (! $config) {
                return true; // No rate limiting configured
            }

            $now = now();
            $windowStart = $now->copy()->subSeconds($config->window_seconds);

            // Find or create rate limit record
            $rateLimit = RateLimit::where('key', $key)
                ->where('action', $action)
                ->where('window_start', '<=', $now)
                ->where('window_end', '>', $now)
                ->first();

            if ($rateLimit) {
                // Update existing record
                $rateLimit->increment('hits');
                $rateLimit->update([
                    'metadata' => array_merge($rateLimit->metadata ?? [], $metadata),
                    'updated_at' => $now,
                ]);

                // Check if limit exceeded
                if ($rateLimit->hits > $config->max_attempts) {
                    $this->handleRateLimitExceeded($rateLimit, $config, $user, $ip);

                    return false;
                }
            } else {
                // Create new rate limit record
                $rateLimit = RateLimit::create([
                    'key' => $key,
                    'action' => $action,
                    'hits' => 1,
                    'max_attempts' => $config->max_attempts,
                    'window_start' => $windowStart,
                    'window_end' => $now->copy()->addSeconds($config->window_seconds),
                    'reset_at' => $now->copy()->addSeconds($config->window_seconds),
                    'metadata' => $metadata,
                ]);
            }

            // Cache the rate limit data
            $this->cacheRateLimit($key, $action, $rateLimit);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to record rate limit action', [
                'action' => $action,
                'key' => $key,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);

            return true; // Fail open
        }
    }

    /**
     * Get current rate limit status from cache or database
     */
    private function getCurrentRateLimit(string $key, string $action, RateLimitConfig $config): array
    {
        $cacheKey = self::CACHE_PREFIX."{$key}:{$action}";

        // Try cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Query database
        $now = now();
        $rateLimit = RateLimit::where('key', $key)
            ->where('action', $action)
            ->where('window_start', '<=', $now)
            ->where('window_end', '>', $now)
            ->first();

        if ($rateLimit) {
            $data = [
                'hits' => $rateLimit->hits,
                'reset_at' => $rateLimit->reset_at,
                'is_blocked' => $rateLimit->is_blocked,
            ];
        } else {
            $data = [
                'hits' => 0,
                'reset_at' => $now->copy()->addSeconds($config->window_seconds),
                'is_blocked' => false,
            ];
        }

        // Cache the result
        Cache::put($cacheKey, $data, self::CACHE_TTL);

        return $data;
    }

    /**
     * Cache rate limit data
     */
    private function cacheRateLimit(string $key, string $action, RateLimit $rateLimit): void
    {
        $cacheKey = self::CACHE_PREFIX."{$key}:{$action}";
        $data = [
            'hits' => $rateLimit->hits,
            'reset_at' => $rateLimit->reset_at,
            'is_blocked' => $rateLimit->is_blocked,
        ];

        Cache::put($cacheKey, $data, self::CACHE_TTL);
    }

    /**
     * Get rate limit configuration
     */
    private function getRateLimitConfig(string $action): ?RateLimitConfig
    {
        $cacheKey = "rate_limit_config:{$action}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($action) {
            return RateLimitConfig::where('action_name', $action)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * Handle when rate limit is exceeded
     */
    private function handleRateLimitExceeded(
        RateLimit $rateLimit,
        RateLimitConfig $config,
        ?User $user = null,
        ?string $ip = null
    ): void {
        try {
            // Mark as blocked
            $rateLimit->update(['is_blocked' => true]);

            // Apply penalty duration if configured
            if ($config->penalty_duration_seconds) {
                $rateLimit->update([
                    'reset_at' => now()->addSeconds($config->penalty_duration_seconds),
                ]);
            }

            // Log the rate limit violation
            Log::warning('Rate limit exceeded', [
                'action' => $rateLimit->action,
                'key' => $rateLimit->key,
                'hits' => $rateLimit->hits,
                'max_attempts' => $rateLimit->max_attempts,
                'user_id' => $user?->id,
                'ip' => $ip,
            ]);

            // Apply escalating penalties if configured
            if ($config->escalation_rules) {
                $this->applyEscalatingPenalty($config, $user, $ip, $rateLimit);
            }

        } catch (Exception $e) {
            Log::error('Failed to handle rate limit exceeded', [
                'rate_limit_id' => $rateLimit->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Apply escalating penalties based on violation history
     */
    private function applyEscalatingPenalty(
        RateLimitConfig $config,
        ?User $user = null,
        ?string $ip = null,
        ?RateLimit $rateLimit = null
    ): void {
        $escalationRules = $config->escalation_rules;

        if ($user) {
            // Count recent violations for this user
            $recentViolations = RateLimit::where('key', 'like', "user:{$user->id}%")
                ->where('is_blocked', true)
                ->where('created_at', '>', now()->subHours(24))
                ->count();

            // Apply user penalty based on violation count
            foreach ($escalationRules['user_penalties'] ?? [] as $rule) {
                if ($recentViolations >= $rule['violation_count']) {
                    $this->applyUserPenalty($user, $rule, $rateLimit);
                    break;
                }
            }
        }

        if ($ip) {
            // Apply IP restrictions
            $this->applyIpRestriction($ip, $escalationRules, $rateLimit);
        }
    }

    /**
     * Apply penalty to user
     */
    private function applyUserPenalty(User $user, array $penaltyRule, ?RateLimit $rateLimit = null): void
    {
        try {
            $expiresAt = null;
            if (isset($penaltyRule['duration_hours'])) {
                $expiresAt = now()->addHours($penaltyRule['duration_hours']);
            }

            UserPenalty::create([
                'user_id' => $user->id,
                'penalty_type' => $penaltyRule['penalty_type'] ?? 'rate_limit',
                'reason' => $penaltyRule['reason'] ?? 'Excessive rate limit violations',
                'description' => $penaltyRule['description'] ?? null,
                'restrictions' => $penaltyRule['restrictions'] ?? [],
                'severity_level' => $penaltyRule['severity_level'] ?? 1,
                'starts_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            Log::info('User penalty applied', [
                'user_id' => $user->id,
                'penalty_type' => $penaltyRule['penalty_type'] ?? 'rate_limit',
                'expires_at' => $expiresAt,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to apply user penalty', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Apply IP restriction
     */
    private function applyIpRestriction(string $ip, array $escalationRules, ?RateLimit $rateLimit = null): void
    {
        try {
            $existingRestriction = IpRestriction::where('ip_address', $ip)
                ->where('is_active', true)
                ->first();

            if ($existingRestriction) {
                // Escalate existing restriction
                $existingRestriction->increment('violation_count');
                $existingRestriction->update(['last_violation_at' => now()]);
            } else {
                // Create new IP restriction
                $ipRules = $escalationRules['ip_penalties'] ?? [];
                $rule = $ipRules[0] ?? [
                    'restriction_type' => 'rate_limit',
                    'duration_hours' => 1,
                    'reason' => 'Rate limit violations',
                ];

                $expiresAt = null;
                if (isset($rule['duration_hours'])) {
                    $expiresAt = now()->addHours($rule['duration_hours']);
                }

                IpRestriction::create([
                    'ip_address' => $ip,
                    'restriction_type' => $rule['restriction_type'],
                    'reason' => $rule['reason'],
                    'description' => $rule['description'] ?? null,
                    'restriction_settings' => $rule['restrictions'] ?? [],
                    'first_violation_at' => now(),
                    'last_violation_at' => now(),
                    'expires_at' => $expiresAt,
                ]);
            }

            Log::info('IP restriction applied', [
                'ip_address' => $ip,
                'restriction_type' => $rule['restriction_type'] ?? 'rate_limit',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to apply IP restriction', [
                'ip_address' => $ip,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if user has active penalty for action
     */
    private function hasActivePenalty(User $user, string $action): bool
    {
        return UserPenalty::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) use ($action) {
                $query->whereJsonContains('restrictions->actions', $action)
                    ->orWhere('penalty_type', 'like', "%{$action}%");
            })
            ->exists();
    }

    /**
     * Get user penalty duration
     */
    private function getUserPenaltyDuration(User $user, string $action): ?int
    {
        $penalty = UserPenalty::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($penalty && $penalty->expires_at) {
            return $penalty->expires_at->diffInSeconds(now());
        }

        return null;
    }

    /**
     * Check if IP has restrictions
     */
    private function hasIpRestriction(string $ip): bool
    {
        return IpRestriction::where('ip_address', $ip)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Get IP restriction duration
     */
    private function getIpRestrictionDuration(string $ip): ?int
    {
        $restriction = IpRestriction::where('ip_address', $ip)
            ->where('is_active', true)
            ->first();

        if ($restriction && $restriction->expires_at) {
            return $restriction->expires_at->diffInSeconds(now());
        }

        return null;
    }

    /**
     * Clean up expired rate limits and restrictions
     */
    public function cleanupExpired(): int
    {
        $cleaned = 0;

        try {
            // Clean up expired rate limits
            $expiredRateLimits = RateLimit::where('window_end', '<', now()->subHours(24))->delete();
            $cleaned += $expiredRateLimits;

            // Clean up expired user penalties
            $expiredPenalties = UserPenalty::where('is_active', true)
                ->where('expires_at', '<', now())
                ->update(['is_active' => false]);
            $cleaned += $expiredPenalties;

            // Clean up expired IP restrictions
            $expiredIpRestrictions = IpRestriction::where('is_active', true)
                ->where('expires_at', '<', now())
                ->update(['is_active' => false]);
            $cleaned += $expiredIpRestrictions;

            Log::info('Rate limit cleanup completed', [
                'cleaned_records' => $cleaned,
            ]);

        } catch (Exception $e) {
            Log::error('Rate limit cleanup failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $cleaned;
    }

    /**
     * Get rate limit status for user
     */
    public function getUserRateStatus(User $user): array
    {
        $status = [
            'active_penalties' => [],
            'recent_violations' => 0,
            'current_limits' => [],
        ];

        // Get active penalties
        $penalties = UserPenalty::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        foreach ($penalties as $penalty) {
            $status['active_penalties'][] = [
                'type' => $penalty->penalty_type,
                'reason' => $penalty->reason,
                'expires_at' => $penalty->expires_at,
                'severity_level' => $penalty->severity_level,
            ];
        }

        // Count recent violations
        $status['recent_violations'] = RateLimit::where('key', 'like', "user:{$user->id}%")
            ->where('is_blocked', true)
            ->where('created_at', '>', now()->subHours(24))
            ->count();

        // Get current rate limits
        $activeConfigs = RateLimitConfig::where('is_active', true)->get();
        foreach ($activeConfigs as $config) {
            $key = "user:{$user->id}";
            $current = $this->getCurrentRateLimit($key, $config->action_name, $config);

            $status['current_limits'][] = [
                'action' => $config->action_name,
                'hits' => $current['hits'],
                'max_attempts' => $config->max_attempts,
                'window_seconds' => $config->window_seconds,
                'reset_at' => $current['reset_at'],
            ];
        }

        return $status;
    }

    /**
     * Generate rate limit key based on scope
     */
    public function generateKey(string $scope, Request $request, ?User $user = null): string
    {
        return match ($scope) {
            'per_user' => $user ? "user:{$user->id}" : "guest:{$request->ip()}",
            'per_ip' => "ip:{$request->ip()}",
            'per_conversation' => $user ? "user:{$user->id}:conversation:{$request->route('conversation')}" : "guest:{$request->ip()}",
            'global' => 'global',
            default => "custom:{$scope}"
        };
    }
}
